<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceDeliveryStatusResource;
use App\Models\Invoice;
use App\Services\Dispatch\DispatchService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * MOD-09 · Saldo pendiente de entrega de una factura (RF-09-02).
 *
 * Solo lectura y derivado: por línea expone facturado, retirado y pendiente, y el estado
 * consolidado (pendiente/parcial/completado) sobre las líneas entregables. No acepta
 * cantidades del cliente.
 */
final class InvoiceDeliveryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly DispatchService $dispatches)
    {
    }

    /** GET /invoices/{invoice}/delivery-status */
    public function show(Invoice $invoice): InvoiceDeliveryStatusResource
    {
        $this->authorize('viewDeliveryStatus', $invoice);

        return new InvoiceDeliveryStatusResource($this->dispatches->estadoEntrega($invoice));
    }
}
