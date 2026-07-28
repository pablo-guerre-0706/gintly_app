<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Envuelve el array de DispatchService::estadoEntrega().
 * El controlador hace: new InvoiceDeliveryStatusResource($service->estadoEntrega($invoice)).
 */
final class InvoiceDeliveryStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'invoice_id'     => $this->resource['invoice_id'],
            'delivery_state' => $this->resource['delivery_state']->value,
            'delivery_label' => $this->resource['delivery_state']->label(),
            'lines'          => $this->resource['lines'], // Arrays con cantidades string.
        ];
    }
}
