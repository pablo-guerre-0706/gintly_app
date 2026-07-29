<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Envuelve el array de ReturnService::saldoAFavor().
 * El controlador hace: new CustomerCreditBalanceResource($service->saldoAFavor($customer)).
 */
final class CustomerCreditBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id'              => $this->resource['customer_id'],
            'available_credit_balance' => $this->resource['available_credit_balance'],
            'open_credit_notes'        => CreditNoteResource::collection($this->resource['open_credit_notes']),
        ];
    }
}
