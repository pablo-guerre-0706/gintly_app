<?php

namespace App\Services\Sales;

use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\SaleStatus;
use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Exceptions\IncompletePaymentException;
use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\SaleItem;
use App\Models\Warehouse;
use App\Services\Cash\CashService;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CashService $cash,
        private readonly \App\Services\Receivable\ReceivableService $receivable,   // ← nuevo
    ) {
    }

    /**
     * Emite una factura a partir de ventas confirmadas. Orquesta TODO el núcleo del sistema:
     * folio secuencial, IVA, reserva de stock, cobro y su reflejo en caja — atómico.
     *
     * @throws IncompletePaymentException
     */
    public function facturar(array $data): Invoice
    {
        // 1) Cargar y validar las ventas (mismo tenant/sucursal/cliente, confirmadas).
        $sales = \App\Models\Sale::query()
            ->with('items.product')
            ->whereIn('id', $data['sale_ids'])
            ->get();

        $this->assertSalesInvoiceable($sales, $data);

        $customerId = $sales->first()->customer_id;
        $branchId   = $sales->first()->branch_id;
        $type       = InvoicePaymentType::from($data['payment_type']);

        // A crédito no se factura al genérico (RF-08-01, validable ya).
        if ($type === InvoicePaymentType::Credito && $sales->first()->customer->is_generic) {
            throw new DomainException('No se puede emitir venta a crédito al Consumidor Final.');
        }

        // 2) Totales (operación pura, antes de escribir): subtotal, IVA, total.
        $discount = (string) ($data['discount_amount'] ?? '0.00');
        $totals   = $this->calcularTotales($sales, $branchId, $discount);

        // Validacion pura de credito
        if ($type === InvoicePaymentType::Credito) {
            $this->receivable->assertCreditAvailable(
                $sales->first()->customer,
                $totals['total'],
                (bool) ($data['owner_authorized'] ?? false),
            );
        }

        // 3) Integridad de cobro (validación PURA: si falla, NO se persiste nada — ERR-07).
        $paid = $this->sumarPagos($data['payments'] ?? []);
        if ($type->requiresFullPayment() && bccomp($paid, $totals['total'], 2) !== 0) {
            throw new IncompletePaymentException;   // rechazo limpio, sin efectos
        }

        // 4) Persistencia atómica.
        return DB::transaction(function () use ($sales, $data, $type, $customerId, $branchId, $totals, $paid) {
            $folio = $this->generarFolio($sales->first()->business_id);   // lock sobre la secuencia

            $invoice = Invoice::create([
                'branch_id'       => $branchId,
                'customer_id'     => $customerId,
                'cash_session_id' => $data['cash_session_id'] ?? null,
                'issued_by'       => $data['user_id'],
                'payment_type'    => $type,
                'subtotal'        => $totals['subtotal'],
                'tax_amount'      => $totals['tax'],
                'discount_amount' => $totals['discount'],
                'total'           => $totals['total'],
                'paid_amount'     => $paid,
                'payment_status'  => $this->derivarPaymentStatus($paid, $totals['total']),
                'issued_at'       => now(),
            ]);
            $invoice->folio  = $folio;                 // asignación directa (fuera de fillable)
            $invoice->status = InvoiceStatus::Emitida;
            $invoice->save();

            // Puente N:M + cierre de las ventas (ya no se pueden re-facturar).
            foreach ($sales as $sale) {
                $invoice->sales()->attach($sale->id, ['business_id' => $invoice->business_id]);
                $sale->status = SaleStatus::Facturada;
                $sale->save();
            }

            // 5) Reserva de stock (RF-03-04): la facturación COMPROMETE, no descuenta.
            $warehouseId = $this->resolveWarehouse($branchId);
            foreach ($sales as $sale) {
                foreach ($sale->items as $item) {
                    $this->reservarStockDeItem($item, $warehouseId, $invoice->issued_by);
                }
            }

            // 6) Cobros: registro fiscal (invoice_payments) + reflejo en la gaveta si es efectivo.
            foreach ($data['payments'] ?? [] as $p) {
                $method = PaymentMethod::from($p['method']);
                $invoice->payments()->create([
                    'cash_session_id' => $data['cash_session_id'] ?? null,
                    'user_id'         => $invoice->issued_by,
                    'payment_method'  => $method,
                    'amount'          => (string) $p['amount'],
                    'reference'       => $p['reference'] ?? null,
                    'paid_at'         => now(),
                ]);

                // Solo el efectivo entra a la gaveta física (concilia con el arqueo, MOD-11).
                if ($method->affectsCashDrawer() && ! empty($data['cash_session_id'])) {
                    $this->cash->registrarMovimiento(
                        sessionId:     $data['cash_session_id'],
                        type:          CashMovementType::Ingreso,
                        category:      CashMovementCategory::Venta,
                        paymentMethod: $method,
                        amount:        (string) $p['amount'],
                        userId:        $invoice->issued_by,
                        saleId:        $sales->first()->id,
                        description:   'Cobro factura '.$invoice->folio,
                    );
                }
            }

            // 7) Crédito → generar CxC (DIFERIDO a MOD-08).
            if ($type === InvoicePaymentType::Credito) {
                $this->receivable->generarDesdeFactura($invoice);
            }
            return $invoice->load('payments', 'sales');
        });
    }

    /**
     * Anulación (RF-07-01): potestad ROL-01 (Policy diferida). Libera las reservas y marca
     * 'anulada' conservando folio y pista de auditoría. NUNCA borra físicamente (BR-04).
     */
    public function anular(Invoice $invoice, int $voidedBy, string $reason): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Emitida) {
            throw new DomainException('Solo una factura emitida puede anularse.');
        }

        return DB::transaction(function () use ($invoice, $voidedBy, $reason) {
            $warehouseId = $this->resolveWarehouse($invoice->branch_id);

            // MOD 09: Se consulta la relación y se itera para calcular el remanente no despachado
            foreach ($invoice->sales()->with('items')->get() as $sale) {
                foreach ($sale->items as $item) {
                    $pendiente = bcsub((string) $item->quantity, (string) $item->dispatched_quantity, 3);
                    if (bccomp($pendiente, '0', 3) > 0) {
                        $this->liberarReservaDeItemParcial($item, $warehouseId, $pendiente);
                    }
                }
            }
        
            $invoice->status      = InvoiceStatus::Anulada;   // el guard permite estos campos
            $invoice->voided_by   = $voidedBy;
            $invoice->voided_at   = now();
            $invoice->void_reason = $reason;
            $invoice->save();

            $this->receivable->revertirPorAnulacion($invoice, $voidedBy);
            // TODO (MOD-10): reembolso de pagos en efectivo (resarcimiento).
            return $invoice;
        });
    }

    // ─────── Helpers privados ───────

    private function assertSalesInvoiceable($sales, array $data): void
    {
        if ($sales->isEmpty()) {
            throw new DomainException('No hay ventas para facturar.');
        }
        if ($sales->pluck('customer_id')->unique()->count() > 1
            || $sales->pluck('branch_id')->unique()->count() > 1) {
            throw new DomainException('Todas las ventas deben ser del mismo cliente y sucursal.');
        }
        foreach ($sales as $sale) {
            if ($sale->status !== SaleStatus::Confirmada) {
                throw new DomainException("La venta {$sale->code} no está confirmada.");
            }
        }
    }

    /** IVA = base gravable (ítems is_taxable) × business.tax_rate. total = subtotal + IVA − descuento. */
    private function calcularTotales($sales, int $branchId, string $discount): array
    {
        $subtotal    = '0.00';
        $taxableBase = '0.00';

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $subtotal = bcadd($subtotal, (string) $item->line_total, 2);
                if ($item->product->is_taxable) {
                    $taxableBase = bcadd($taxableBase, (string) $item->line_total, 2);
                }
            }
        }

        $taxRate = (string) $sales->first()->business->tax_rate;   // IVA por negocio
        $tax     = bcmul($taxableBase, $taxRate, 2);
        $total   = bcsub(bcadd($subtotal, $tax, 2), $discount, 2);

        return ['subtotal' => $subtotal, 'tax' => $tax, 'discount' => $discount, 'total' => $total];
    }

    private function sumarPagos(array $payments): string
    {
        $sum = '0.00';
        foreach ($payments as $p) {
            $sum = bcadd($sum, (string) $p['amount'], 2);
        }
        return $sum;
    }

    private function derivarPaymentStatus(string $paid, string $total): InvoicePaymentStatus
    {
        if (bccomp($paid, $total, 2) >= 0) return InvoicePaymentStatus::Pagada;
        if (bccomp($paid, '0', 2) > 0)     return InvoicePaymentStatus::Parcial;
        return InvoicePaymentStatus::Pendiente;
    }

    /** Folio secuencial atómico: bloquea SOLO la fila de la secuencia (no la tabla de facturas). */
    private function generarFolio(int $businessId): string
    {
        $seq = DocumentSequence::query()
            ->where('business_id', $businessId)
            ->where('document_type', 'invoice')
            ->lockForUpdate()
            ->firstOrFail();   // sembrada por el BusinessObserver

        $number = $seq->next_number;
        $seq->next_number = $number + 1;
        $seq->save();

        return $seq->prefix.str_pad((string) $number, 8, '0', STR_PAD_LEFT);   // ej. F-00000042
    }

    /** Reserva según naturaleza: simple directo, compuesto por su receta congelada, servicio nada. */
    private function reservarStockDeItem(SaleItem $item, int $warehouseId, int $userId): void
    {
        $product = $item->product;

        if ($product->type === ProductType::Service) {
            return;
        }
        if ($product->type === ProductType::Simple) {
            if ($product->tracks_inventory) {
                $this->inventory->reservar($product->id, $warehouseId, (string) $item->quantity, $userId);
            }
            return;
        }
        // Compuesto: reserva cada insumo del snapshot × cantidad vendida.
        foreach (($item->recipe_snapshot ?? []) as $line) {
            $needed = bcmul((string) $line['quantity'], (string) $item->quantity, 3);
            $this->inventory->reservar((int) $line['ingredient_id'], $warehouseId, $needed, $userId);
        }
    }

    /** Espejo exacto de la reserva, para la anulación. */
    private function liberarReservaDeItem(SaleItem $item, int $warehouseId): void
    {
        $product = $item->product;
        if ($product->type === ProductType::Service) return;

        if ($product->type === ProductType::Simple) {
            if ($product->tracks_inventory) {
                $this->inventory->liberarReserva($product->id, $warehouseId, (string) $item->quantity);
            }
            return;
        }
        foreach (($item->recipe_snapshot ?? []) as $line) {
            $needed = bcmul((string) $line['quantity'], (string) $item->quantity, 3);
            $this->inventory->liberarReserva((int) $line['ingredient_id'], $warehouseId, $needed);
        }
    }

    /** La reserva sale de la bodega POR DEFECTO de la sucursal (candado default_lock, MOD-03). */
    private function resolveWarehouse(int $branchId): int
    {
        $warehouse = Warehouse::query()
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->first();

        if ($warehouse === null) {
            throw new DomainException('La sucursal no tiene bodega por defecto asignada.');
        }
        return $warehouse->id;
    }
}
