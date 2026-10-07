<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CheckoutIntent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resultado de contratación: URL del checkout ALOJADO del proveedor y plan/periodicidad elegidos. No expone
 * variante, tienda ni credenciales. El retorno del navegador NO activa acceso (lo decide el webhook verificado).
 *
 * @mixin CheckoutIntent
 */
final class CheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'checkout_url' => $this->checkout_url,
            'plan_key'     => $this->plan_key,
            'period'       => $this->period?->value,
            'status'       => $this->status,
            'expires_at'   => $this->expires_at?->toIso8601String(), // vencimiento FINITO de la URL
        ];
    }
}
