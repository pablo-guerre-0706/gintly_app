<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Envuelve el array de ReceivableService::evaluarCredito(). */
final class CreditCheckResource extends JsonResource
{
    /** @param  \Illuminate\Http\Request  $request */
    public function toArray($request): array
    {
        return [
            'approved'                     => (bool) $this->resource['approved'],
            'exposure'                     => (string) $this->resource['exposure'],
            'limit'                        => (string) $this->resource['limit'],
            'available'                    => (string) $this->resource['available'],
            'requires_owner_authorization' => (bool) $this->resource['requires_owner_authorization'],
        ];
    }
}
