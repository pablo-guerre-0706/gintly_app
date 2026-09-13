<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Envuelve el array de ReceivableService::estadoDeCredito().
 * El controlador hace: new CustomerCreditStatusResource($receivables->estadoDeCredito($customer)).
 */
final class CustomerCreditStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'credit_limit'     => $this->resource['credit_limit'],
            'exposure'         => $this->resource['exposure'],
            'available_credit' => $this->resource['available_credit'],
            'open_accounts'    => AccountReceivableResource::collection($this->resource['open_accounts']),
            'payment_history'  => ReceivablePaymentResource::collection($this->resource['payment_history']),
        ];
    }
}
