<?php

declare(strict_types=1);

namespace App\Services\Returns;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Consulta de SOLO LECTURA para preparar devoluciones (MOD-10). Fuente autorizada para que el BODEGUERO
 * (capacidad devoluciones.crear, SIN facturas.ver) descubra las facturas y líneas realmente devolvibles
 * de SU sucursal, sin acceso general a /invoices. Es informativa: POST /sales-returns revalida todo
 * dentro de su transacción (no se confía en esta respuesta).
 *
 * Devolvible por línea = dispatched_quantity − returned_quantity (> 0), idéntico a la regla que aplica
 * ReturnService (SaleItem::returnableQuantity). Servicios/no entregados (dispatched 0) y líneas totalmente
 * devueltas quedan excluidos naturalmente; las facturas anuladas se excluyen por estado.
 */
final class ReturnEligibilityService
{
    /** Facturas de la sucursal del actor con al menos una línea devolvible. ROL-01/02: alcance de negocio. */
    public function invoices(User $actor, int $perPage): LengthAwarePaginator
    {
        return Invoice::query()
            ->forOperator($actor) // ROL-03 → su sucursal; ROL-01/ROL-02 → negocio (BusinessScope).
            ->where('status', '!=', InvoiceStatus::Anulada->value)
            ->whereHas('sales.items', fn ($q) => $q->whereColumn('dispatched_quantity', '>', 'returned_quantity'))
            ->with('customer')
            ->orderByDesc('issued_at')
            ->paginate($perPage);
    }

    /**
     * Resuelve una factura elegible del alcance del actor (sucursal + no anulada) o falla con 404,
     * sin revelar la existencia de facturas ajenas (misma convención multitenant/sucursal).
     */
    public function eligibleInvoiceOrFail(User $actor, int $invoiceId): Invoice
    {
        return Invoice::query()
            ->forOperator($actor)
            ->where('status', '!=', InvoiceStatus::Anulada->value)
            ->whereKey($invoiceId)
            ->firstOrFail();
    }

    /**
     * Líneas devolvibles de una factura (dispatched − returned > 0).
     *
     * @return Collection<int, SaleItem>
     */
    public function returnableItems(Invoice $invoice): Collection
    {
        $saleIds = $invoice->sales()->pluck('sales.id');

        return SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->whereColumn('dispatched_quantity', '>', 'returned_quantity')
            ->with('product')
            ->orderBy('id')
            ->get();
    }
}
