<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 6 · Contrato canónico de GET /api/v1/me. Devuelve identidad, rol humano garantizado, sucursal,
 * perfiles operativos, capacidades EFECTIVAS de interfaz, contexto mínimo del negocio y estado activo.
 *
 * Las capacidades son generales de interfaz (no afirman autorización sobre un recurso concreto: las
 * Policies siguen siendo la autoridad). business_id proviene SIEMPRE de la sesión, nunca del frontend.
 *
 * @mixin User
 */
final class MeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'is_active'    => (bool) $user->is_active,
            'role'         => $user->getRoleNames()->first(), // rol humano garantizado (login exige rol; ROL-SYS bloqueado).
            'branch_id'    => $user->branch_id,
            'profiles'     => $user->profileValues(),          // solo ROL-03 tendrá perfiles.
            'capabilities' => $user->effectiveCapabilities(),   // capacidades de interfaz (no autorización por recurso).
            'business'     => [
                'id'       => $user->business_id,              // desde la sesión, no del frontend.
                'name'     => $user->business?->name,
                'timezone' => $user->business?->timezone,
                'status'   => $user->business?->status?->value,
            ],
        ];
    }
}
