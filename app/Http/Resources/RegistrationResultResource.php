<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Respuesta pública del alta: EXCLUSIVAMENTE el slug del negocio y el correo del propietario. Nunca IDs
 * internos, contraseña, hash, permisos, roles, tokens ni detalles de aprovisionamiento.
 *
 * @property array{business_slug: string, owner_email: string} $resource
 */
final class RegistrationResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'business_slug' => $this->resource['business_slug'],
            'owner_email'   => $this->resource['owner_email'],
        ];
    }
}
