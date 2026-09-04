<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\DocumentSequenceType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\FolioConflictException;
use App\Exceptions\IncompletePaymentException;
use App\Exceptions\InvalidInvoiceStateException;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Cash\CashService;
use App\Services\Inventory\InventoryService;
use App\Services\Receivable\ReceivableService;
use App\Support\FolioGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orquestación transaccional de la facturación: el punto de mayor densidad
 * del sistema. TODO-O-NADA real (D-27): la totalidad del flujo —carga y lock
 * de ventas, verificación de crédito, folio, persistencia, reserva de stock,
 * pagos y CxC— ocurre dentro de una única DB::transaction. Cualquier fallo
 * revierte la operación completa.
 */
final class InvoiceService
{
    private const QTY_SCALE = 3;

    private const MONEY_SCALE = 2;

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CashService $cash,
        private readonly FolioGenerator $folios,
        private readonly ReceivableService $receivables,
    ) {
    }

    /**
     * @param array{
     *     sale_ids: array<int, int>,
     *     payment_type: string,
     *     cash_session_id: ?int,
     *     discount_amount: ?string,
     *     owner_authorized: ?bool,
     *     payments: array<int, array{method: string, amount: string, reference: ?string}>
     * } $data
     */
    public function facturar(array $data, User $actor): Invoice
    {
        return DB::transaction(function () use ($data, $actor): Invoice {
            $businessId     = (int) $actor->business_id;
            $saleIds        = $data['sale_ids'];
            $paymentType    = InvoicePaymentType::from($data['payment_type']);
            $cashSessionId  = $data['cash_session_id'] ?? null;
            $discountAmount = (string) ($data['discount_amount'] ?? '0.00');
            $payments       = $data['payments'] ?? [];

            // --- 1. Ventas bajo lock, homogéneas y confirmadas ---
            $sales = Sale::query()
                ->where('business_id', $businessId)
                ->whereIn('id', $saleIds)
                ->lockForUpdate()
                ->get();

            $this->assertHomogeneous($sales);

            $customerId = (int) $sales->first()->customer_id;
            $branchId   = (int) $sales->first()->branch_id;

            // --- 2. Totales: subtotal, IVA (solo gravable) y total con descuento (H-68) ---
            $business = Business::query()->whereKey($businessId)->firstOrFail();
            $totals   = $this->computeTotals($sales, (string) $business->tax_rate, $discountAmount);

            // --- 3. Crédito: verificación ATÓMICA (dentro de la tx, tras el lock) ---
            //     owner_authorized (ROL-01 para exceder cupo) se lee del request validado.
            if ($paymentType === InvoicePaymentType::Credito) {
                $this->assertNotGenericCustomer($customerId, $businessId);

                $customer = Customer::query()
                    ->where('business_id', $businessId)
                    ->findOrFail($customerId);

                $this->receivables->assertCreditAvailable(
                    $customer,
                    $totals['total'],
                    (bool) ($data['owner_authorized'] ?? false),
                );
            }

            // --- 4. Contado exige pago completo (H-64, ERR-07). Rollback si no ---
            $paidAmount = $this->sumPayments($payments);
            if ($paymentType->requiresFullPayment()
                && bccomp($paidAmount, $totals['total'], self::MONEY_SCALE) !== 0
            ) {
                // D-27 · La excepción dentro de la tx revierte TODO.
                throw IncompletePaymentException::make($paidAmount, $totals['total']);
            }

            // --- 5. Folio fiscal bajo lock de la fila del contador (H-63) ---
            try {
                $folio = $this->folios->next($businessId, DocumentSequenceType::Invoice);
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    // Clase standalone (409). Ajusta a tu factoría real si no es make().
                    throw FolioConflictException::make();
                }
                throw $e;
            }

            // --- 6. Crear la factura ---
            $invoice = new Invoice([
                'branch_id'       => $branchId,
                'customer_id'     => $customerId,
                'cash_session_id' => $cashSessionId,
                'folio'           => $folio,
                'payment_type'    => $paymentType,
                'subtotal'        => $totals['subtotal'],
                'tax_amount'      => $totals['tax'],
                'discount_amount' => $discountAmount,
                'total'           => $totals['total'],
                'issued_at'       => Carbon::now(),
            ]);
            $invoice->issued_by      = $actor->id;
            $invoice->status         = InvoiceStatus::Emitida;
            $invoice->paid_amount    = $paidAmount;
            $invoice->payment_status = InvoicePaymentStatus::fromAmounts($paidAmount, $totals['total']);
            $invoice->save();

            // Puente N:M con business_id explícito en el pivote (invoice_sale lo exige).
            $pivot = $sales->pluck('id')
                ->mapWithKeys(fn ($id) => [$id => ['business_id' => $businessId]])
                ->all();
            $invoice->sales()->attach($pivot);

            // --- 7. Reserva de stock (H-60): simples e insumos de compuestos ---
            $this->reserveStock($businessId, $branchId, $sales);

            // --- 8. Pagos + movimiento de caja de efectivo (H-65) ---
            $this->registerPayments($actor, $invoice, $cashSessionId, $payments, $sales->first());

            // --- 9. Marcar ventas como facturadas ---
            Sale::query()->whereIn('id', $sales->pluck('id'))
                ->update(['status' => SaleStatus::Facturada->value]);

            // --- 10. Crédito: generar la CxC atómicamente (RF-08-01) ---
            if ($paymentType === InvoicePaymentType::Credito) {
                $this->receivables->generarDesdeFactura($invoice);
            }

            return $invoice->refresh()->load(['sales', 'payments']);
        });
    }

    /**
     * Anulación por ROL-01 (BR-06). Libera las reservas de stock, marca 'anulada'
     * conservando folio y auditoría, y revierte la CxC. La reversión de efectivo
     * (MOD-10) se orquesta en su módulo.
     */
    public function anular(Invoice $invoice, string $reason, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $actor): Invoice {
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if (! $invoice->status->canVoid()) {
                throw InvalidInvoiceStateException::invoiceNotVoidable($invoice->id);
            }

            // Liberar reservas de todas las líneas de las ventas asociadas.
            $sales = $invoice->sales()->with('items')->get();
            $this->releaseStock($invoice->business_id, $invoice->branch_id, $sales);

            // Mutación controlada (D-29 permite status/voided_*; el núcleo fiscal no se toca).
            $invoice->status      = InvoiceStatus::Anulada;
            $invoice->voided_by   = $actor->id;
            $invoice->voided_at   = Carbon::now();
            $invoice->void_reason = $reason;
            $invoice->save();

            // Revierte la CxC conservando los abonos (BR-07).
            $ar = $invoice->accountReceivable()->lockForUpdate()->first();
            if ($ar !== null) {
                $this->receivables->revertirPorAnulacion($ar);
            }

            return $invoice->refresh();
        });
    }

    /**
     * Todas las ventas deben existir, estar confirmadas y compartir cliente y sucursal.
     *
     * @param \Illuminate\Support\Collection<int, Sale> $sales
     */
    private function assertHomogeneous($sales): void
    {
        if ($sales->isEmpty()) {
            throw InvalidInvoiceStateException::salesNotHomogeneous();
        }

        foreach ($sales as $sale) {
            if ($sale->status !== SaleStatus::Confirmada) {
                throw InvalidInvoiceStateException::saleNotConfirmed($sale->id);
            }
        }

        $customers = $sales->pluck('customer_id')->unique();
        $branches  = $sales->pluck('branch_id')->unique();

        if ($customers->count() > 1 || $branches->count() > 1) {
            throw InvalidInvoiceStateException::salesNotHomogeneous();
        }
    }

    private function assertNotGenericCustomer(int $customerId, int $businessId): void
    {
        $isGeneric = Customer::query()
            ->where('business_id', $businessId)
            ->whereKey($customerId)
            ->value('is_generic');

        if ($isGeneric) {
            throw InvalidInvoiceStateException::creditToGeneric();
        }
    }

    /**
     * IVA = Σ(line_total de líneas gravables) × tax_rate. total = subtotal + IVA
     * − descuento de factura (H-68), con piso en 0. Todo bcmath a escala 2.
     *
     * @param  \Illuminate\Support\Collection<int, Sale> $sales
     * @return array{subtotal: string, tax: string, total: string}
     */
    private function computeTotals($sales, string $taxRate, string $discountAmount): array
    {
        $subtotal    = '0.00';
        $taxableBase = '0.00';

        foreach ($sales as $sale) {
            foreach ($sale->items()->get(['line_total', 'is_taxable']) as $item) {
                $subtotal = bcadd($subtotal, (string) $item->line_total, self::MONEY_SCALE);

                if ($item->is_taxable) {
                    $taxableBase = bcadd($taxableBase, (string) $item->line_total, self::MONEY_SCALE);
                }
            }
        }

        // tax_rate es fracción (0.15 = 15 %). IVA a escala 2.
        $tax   = bcmul($taxableBase, $taxRate, self::MONEY_SCALE);
        $total = bcsub(bcadd($subtotal, $tax, self::MONEY_SCALE), $discountAmount, self::MONEY_SCALE);

        if (bccomp($total, '0', self::MONEY_SCALE) < 0) {
            $total = '0.00';
        }

        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $total];
    }

    /**
     * @param array<int, array{method: string, amount: string, reference: ?string}> $payments
     */
    private function sumPayments(array $payments): string
    {
        $sum = '0.00';

        foreach ($payments as $payment) {
            $sum = bcadd($sum, (string) $payment['amount'], self::MONEY_SCALE);
        }

        return $sum;
    }

    /**
     * Reserva de stock por línea. Simples: reservan su propia cantidad. Compuestos:
     * reservan cada insumo del recipe_snapshot × cantidad de la línea. Bodega por
     * defecto de la sucursal.
     *
     * @param \Illuminate\Support\Collection<int, Sale> $sales
     */
    private function reserveStock(int $businessId, int $branchId, $sales): void
    {
        $warehouseId = $this->defaultWarehouseId($businessId, $branchId);

        foreach ($sales as $sale) {
            foreach ($sale->items()->get() as $item) {
                $this->reserveItem($businessId, $warehouseId, $item);
            }
        }
    }

    private function reserveItem(int $businessId, int $warehouseId, SaleItem $item): void
    {
        if ($item->isCompound()) {
            // Compuesto: reservar cada insumo del snapshot × cantidad vendida.
            foreach ((array) $item->recipe_snapshot as $line) {
                $needed = bcmul((string) $item->quantity, (string) $line['quantity'], self::QTY_SCALE);
                $this->inventory->reservar($businessId, (int) $line['ingredient_id'], $warehouseId, $needed);
            }

            return;
        }

        // Simple: reservar su propia cantidad. Los servicios (sin inventario) se omiten.
        if ($this->productTracksInventory($businessId, $item->product_id)) {
            $this->inventory->reservar($businessId, (int) $item->product_id, $warehouseId, (string) $item->quantity);
        }
    }

    /**
     * Libera las reservas del remanente NO despachado de cada línea (anulación).
     *
     * @param \Illuminate\Support\Collection<int, Sale> $sales
     */
    private function releaseStock(int $businessId, int $branchId, $sales): void
    {
        $warehouseOrigen = $this->defaultWarehouseId($businessId, $branchId);

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $remnant = bcsub((string) $item->quantity, (string) $item->dispatched_quantity, self::QTY_SCALE);

                if (bccomp($remnant, '0.000', self::QTY_SCALE) > 0) {
                    if ($item->isCompound()) {
                        foreach ((array) $item->recipe_snapshot as $line) {
                            $needed = bcmul($remnant, (string) $line['quantity'], self::QTY_SCALE);
                            $this->inventory->liberarReserva($businessId, (int) $line['ingredient_id'], $warehouseOrigen, $needed);
                        }

                        continue;
                    }

                    if ($this->productTracksInventory($businessId, $item->product_id)) {
                        $this->inventory->liberarReserva($businessId, (int) $item->product_id, $warehouseOrigen, $remnant);
                    }
                }
            }
        }
    }

    /**
     * Registra los pagos (invoice_payments) y, por cada pago en efectivo, el
     * cash_movement 'venta' vía CashService (H-65).
     *
     * @param array<int, array{method: string, amount: string, reference: ?string}> $payments
     */
    private function registerPayments(User $actor, Invoice $invoice, ?int $cashSessionId, array $payments, Sale $firstSale): void
    {
        foreach ($payments as $payment) {
            $method = PaymentMethod::from($payment['method']);

            $invoice->payments()->create([
                'cash_session_id' => $cashSessionId,
                'user_id'         => $actor->id,
                'payment_method'  => $method,
                'amount'          => $payment['amount'],
                'reference'       => $payment['reference'] ?? null,
                'paid_at'         => Carbon::now(),
            ]);

            // Solo el efectivo genera movimiento de caja (H-65). El CashService exige
            // y bloquea la sesión abierta; el cash_movement lleva sale_id (P3).
            if ($method === PaymentMethod::Efectivo && $cashSessionId !== null) {
                $this->cash->registrarMovimientoVenta(
                    actor: $actor,
                    cashSessionId: $cashSessionId,
                    amount: $payment['amount'],
                    saleId: $firstSale->id,
                );
            }
        }
    }

    private function defaultWarehouseId(int $businessId, int $branchId): int
    {
        $warehouse = Warehouse::query()
            ->where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->first();

        // Sin bodega default explícita, se toma la primera activa de la sucursal.
        if ($warehouse === null) {
            $warehouse = Warehouse::query()
                ->where('business_id', $businessId)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->firstOrFail();
        }

        return (int) $warehouse->id;
    }

    private function productTracksInventory(int $businessId, int $productId): bool
    {
        return (bool) Product::query()
            ->where('business_id', $businessId)
            ->whereKey($productId)
            ->value('tracks_inventory');
    }
}