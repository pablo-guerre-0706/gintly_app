<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\AccountReceivableResource;
use App\Http\Resources\ReceivablePaymentResource;

/**
 * Envuelve el array de ReceivableService::estadoDeCredito().
 * El controlador hace: new CustomerCreditStatusResource($receivables->estadoDeCredito($customer)).
 */
final class CustomerCreditStatusResource extends JsonResource
{
    /** @param  \Illuminate\Http\Request  $request */
    public function toArray($request): array
    {
        return [
            'credit_limit'     => (string) $this->resource['credit_limit'],
            'exposure'         => (string) $this->resource['exposure'],
            'available_credit' => (string) $this->resource['available_credit'],
            'open_accounts'    => AccountReceivableResource::collection($this->resource['open_accounts']),
            'payment_history'  => ReceivablePaymentResource::collection($this->resource['payment_history']),
        ];
    }
}
