<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Enums\DocumentSequenceType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoicePaymentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Exceptions\IncompletePaymentException;
use App\Exceptions\InvalidInvoiceStateException;
use App\Models\Business;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Services\Inventory\InventoryService;
use App\Services\Receivable\ReceivableService;
use App\Support\FolioGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orquestación transaccional de la facturación: el punto de mayor
 * densidad del sistema. Coordina, atomicamente (rollback estricto):
 *   1. Validación de homogeneidad de ventas (mismo cliente y sucursal).
 *   2. Cálculo de subtotal, IVA (solo líneas gravables) y total.
 *   3. Verificación de pago completo en contado (ERR-07).
 *   4. Folio fiscal bajo lock.
 *   5. Reserva de stock: simples e insumos de compuestos vía recipe_snapshot
 *      congelado NO toca el kardex.
 *   6. invoice_payments + cash_movement 'venta' si hay efectivo.
 *   7. Marca las ventas como facturadas.
 * Cualquier fallo revierte todo (a diferencia de MOD-04/06), la factura es todo o nada real.
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
     * @param  array<int, int>  $saleIds
     * @param  array<int, array{method: string, amount: string, reference: ?string}>  $payments
     */
    public function facturar(
        User $actor,
        array $saleIds,
        InvoicePaymentType $paymentType,
        ?int $cashSessionId,
        string $discountAmount,
        array $payments
    ): Invoice {
        
        $salesForTotals = Sale::query()
            ->where('business_id', $actor->business_id)
            ->whereIn('id', $saleIds)
            ->get();

        $customer = $salesForTotals->first()?->customer; // Obtiene el objeto Customer (o cámbialo por customer_id si tu método pide el ID)
        $total = $salesForTotals->sum('total'); // Suma los totales de las ventas
        $data = []; // Evita que falle por no existir la variable $data

        if ($paymentType === InvoicePaymentType::Credito) {
            $this->receivables->assertCreditAvailable(
                $customer,
                $total,
                (bool) ($data['owner_authorized'] ?? false) // Autorización ROL-01 para exceder.
            );
        }

        return DB::transaction(function () use (
            $actor, $saleIds, $paymentType, $cashSessionId, $discountAmount, $payments
        ): Invoice {
            // --- 1. Ventas bajo lock, homogéneas y confirmadas ---
            $sales = Sale::query()
                ->where('business_id', $actor->business_id)
                ->whereIn('id', $saleIds)
                ->lockForUpdate()
                ->get();

            $this->assertHomogeneous($sales);

            $customerId = (int) $sales->first()->customer_id;
            $branchId = (int) $sales->first()->branch_id;

            $totalAmount = '0.000';
            foreach ($sales as $sale) {
                $totalAmount = bcadd($totalAmount, (string) $sale->total_amount, 3);
            }

            if ($paymentType === InvoicePaymentType::Credito) {
                $this->assertNotGenericCustomer($customerId, $actor->business_id);
                
                $customer = Customer::query()
                    ->where('business_id', $actor->business_id)
                    ->findOrFail($customerId);

                $this->receivables->assertCreditAvailable($customer, $totalAmount); 
            }

            // --- 2. Totales: subtotal, IVA (solo gravable), total (H-68) ---
            $business = Business::query()->whereKey($actor->business_id)->firstOrFail();
            $totals = $this->computeTotals($sales, (string) $business->tax_rate, $discountAmount);

            // --- 3. Contado exige pago completo (H-64, ERR-07). Rollback si no ---
            $paidAmount = $this->sumPayments($payments);

            if ($paymentType->requiresFullPayment()
                && bccomp($paidAmount, $totals['total'], self::MONEY_SCALE) !== 0
            ) {
                // D-27 · La excepción dentro de la tx revierte TODO.
                throw IncompletePaymentException::make($paidAmount, $totals['total']);
            }

            // --- 4. Folio fiscal bajo lock (H-63) ---
            try {
                $folio = $this->folios->next($actor->business_id, DocumentSequenceType::Invoice);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    throw InvalidInvoiceStateException::folioConflict();
                }
                throw $e;
            }

            // --- 5. Crear la factura ---
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
            $invoice->issued_by = $actor->id;
            $invoice->status = InvoiceStatus::Emitida;
            $invoice->paid_amount = $paidAmount;
            $invoice->payment_status = InvoicePaymentStatus::fromAmounts($paidAmount, $totals['total']);
            $invoice->save();

            // Puente N:M con las ventas.
            $invoice->sales()->attach($sales->pluck('id')->all());

            // --- 6. Reserva de stock (H-60): simples e insumos de compuestos ---
            $this->reserveStock($actor->business_id, $branchId, $sales);

            // --- 7. Pagos + movimiento de caja de efectivo (H-65) ---
            $this->registerPayments($actor, $invoice, $cashSessionId, $payments, $sales->first());

            // --- 8. Marcar ventas como facturadas ---
            Sale::query()->whereIn('id', $sales->pluck('id'))->update(['status' => SaleStatus::Facturada->value]);

            // genera la CxC atómicamente (RF-08-01).
            if ($invoice->payment_type === InvoicePaymentType::Credito) {
                $this->receivables->generarDesdeFactura($invoice);
            }

            return $invoice->refresh()->load(['sales', 'payments']);
        });
    }

    /**
     * Anulación por ROL-01. Libera las reservas de stock, marca
     * 'anulada' conservando folio y auditoría. La reversión de CxC (MOD-08) y el
     * reembolso (MOD-10) se orquestan en esos módulos.
     */
    public function anular(User $actor, Invoice $invoice, string $voidReason): Invoice
    {
        return DB::transaction(function () use ($actor, $invoice, $voidReason): Invoice {
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if (! $invoice->status->canVoid()) {
                throw InvalidInvoiceStateException::invoiceNotVoidable($invoice->id);
            }

            // Liberar reservas de todas las líneas de las ventas asociadas.
            $sales = $invoice->sales()->with('items')->get();
            $this->releaseStock($invoice->business_id, $invoice->branch_id, $sales);

            $invoice->status = InvoiceStatus::Anulada;
            $invoice->voided_by = $actor->id;
            $invoice->voided_at = Carbon::now();
            $invoice->void_reason = $voidReason;
            $invoice->save();

            // P8 (MOD-08) · revierte la CxC conservando los abonos.
            $ar = $invoice->accountReceivable()->lockForUpdate()->first();
            if ($ar !== null) {
                $this->receivables->revertirPorAnulacion($ar);
            }
            return $invoice->refresh();
        });
    }

    /**
     * Todas las ventas deben compartir cliente y sucursal.
     * @param  \Illuminate\Support\Collection<int, Sale>  $sales
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
        $branches = $sales->pluck('branch_id')->unique();

        if ($customers->count() > 1 || $branches->count() > 1) {
            throw InvalidInvoiceStateException::salesNotHomogeneous();
        }
    }

    private function assertNotGenericCustomer(int $customerId, int $businessId): void
    {
        $isGeneric = \App\Models\Customer::query()
            ->where('business_id', $businessId)
            ->whereKey($customerId)
            ->value('is_generic');

        if ($isGeneric) {
            throw InvalidInvoiceStateException::creditToGeneric();
        }
    }

    /**
     * IVA = Σ(line_total de líneas gravables) × tax_rate. total = subtotal +
     * IVA − descuento de factura (H-68). Todo bcmath e2.
     *
     * @param  \Illuminate\Support\Collection<int, Sale>  $sales
     * @return array{subtotal: string, tax: string, total: string}
     */
    private function computeTotals($sales, string $taxRate, string $discountAmount): array
    {
        $subtotal = '0.00';
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
        $tax = bcmul($taxableBase, $taxRate, self::MONEY_SCALE);

        $total = bcsub(bcadd($subtotal, $tax, self::MONEY_SCALE), $discountAmount, self::MONEY_SCALE);
        if (bccomp($total, '0', self::MONEY_SCALE) < 0) {
            $total = '0.00';
        }

        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $total];
    }

    /**
     * @param  array<int, array{method: string, amount: string, reference: ?string}>  $payments
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
     * reservan cada insumo del recipe_snapshot × cantidad de la línea.
     * Usa la bodega por defecto de la sucursal.
     * @param  \Illuminate\Support\Collection<int, Sale>  $sales
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

        // Simple: reservar su propia cantidad. Los servicios (sin inventario) se
        // omiten: no tienen stock que comprometer.
        if ($this->productTracksInventory($businessId, $item->product_id)) {
            $this->inventory->reservar($businessId, (int) $item->product_id, $warehouseId, (string) $item->quantity);
        }
    }

    /**
     * Libera las reservas de todas las líneas (anulación).
     * @param  \Illuminate\Support\Collection<int, Sale>  $sales
     */
    private function releaseStock(int $businessId, int $branchId, $sales): void
    {
        $warehouseOrigen = $this->defaultWarehouseId($businessId, $branchId);

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $remnant = bcsub((string) $item->quantity, (string) $item->dispatched_quantity, 3);
                if (bccomp($remnant, '0.000', 3) > 0) {
                    if ($item->isCompound()) {
                        foreach ((array) $item->recipe_snapshot as $line) {
                            $needed = bcmul($remnant, (string) $line['quantity'], 3);
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
     * @param  array<int, array{method: string, amount: string, reference: ?string}>  $payments
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

            // Solo el efectivo genera movimiento de caja (H-65). El CashService
            // exige y bloquea la sesión abierta; el cash_movement lleva sale_id (P3).
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
        $warehouse = \App\Models\Warehouse::query()
            ->where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->first();

        // Sin bodega default explícita, se toma la primera activa de la sucursal.
        if ($warehouse === null) {
            $warehouse = \App\Models\Warehouse::query()
                ->where('business_id', $businessId)
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->firstOrFail();
        }

        return (int) $warehouse->id;
    }

    private function productTracksInventory(int $businessId, int $productId): bool
    {
        return (bool) \App\Models\Product::query()
            ->where('business_id', $businessId)
            ->whereKey($productId)
            ->value('tracks_inventory');
    }
}
