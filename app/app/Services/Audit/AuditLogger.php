<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Escritor único de la bitácora de auditoría (RF-01-03).
 *
 * La tabla es de solo inserción: el modelo AuditLog rechaza update/delete. Este
 * servicio centraliza la construcción del registro para que negocio, usuario,
 * entidad afectada (vía morphMap), valores y dirección de origen se resuelvan
 * de forma consistente en cada flujo de MOD-01.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $old  Estado anterior (nunca secretos).
     * @param  array<string, mixed>|null  $new  Estado nuevo (nunca secretos).
     */
    public function record(
        string $action,
        Model $auditable,
        ?User $actor = null,
        ?array $old = null,
        ?array $new = null,
        ?string $ipAddress = null,
    ): AuditLog {
        $actor ??= $this->resolveActor();

        $log = new AuditLog();

        // forceFill: business_id no está en $fillable (se asigna explícito para
        // no depender del hook de BelongsToBusiness, que solo actúa con sesión).
        $log->forceFill([
            'business_id'    => $this->resolveBusinessId($actor, $auditable),
            'user_id'        => $actor?->getKey(),
            'action'         => $action,
            'auditable_type' => $auditable->getMorphClass(), // alias del morphMap
            'auditable_id'   => $auditable->getKey(),
            'old_values'     => $old,
            'new_values'     => $new,
            'ip_address'     => $ipAddress ?? $this->resolveIp(),
        ]);

        $log->save();

        return $log;
    }

    private function resolveActor(): ?User
    {
        $user = Auth::guard((string) config('gintly.tenant.api_guard', 'web'))->user()
            ?? Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function resolveBusinessId(?User $actor, Model $auditable): ?int
    {
        // Business es la raíz del tenant: su propia PK es el business_id.
        if ($auditable instanceof Business) {
            return (int) $auditable->getKey();
        }

        $businessId = $actor?->business_id ?? $auditable->getAttribute('business_id');

        return $businessId !== null ? (int) $businessId : null;
    }

    private function resolveIp(): ?string
    {
        return request()?->ip();
    }
}
