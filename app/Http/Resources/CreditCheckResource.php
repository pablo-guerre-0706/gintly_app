<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Envuelve el array de ReceivableService::evaluarCredito(). */
final class CreditCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'approved'                     => (bool) $this->resource['approved'],
            'exposure'                     => $this->resource['exposure'],
            'limit'                        => $this->resource['limit'],
            'available'                    => $this->resource['available'],
            'requires_owner_authorization' => (bool) $this->resource['requires_owner_authorization'],
        ];
    }
}
