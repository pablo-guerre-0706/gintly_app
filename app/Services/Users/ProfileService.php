<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\OperativeProfile;
use App\Models\User;
use App\Models\UserOperativeProfile;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3 · Gestión de perfiles operativos de ROL-03 (persistente, normalizada, auditable).
 * Fuente única del catálogo: config/profiles.php (OperativeProfile). Reemplazo atómico del conjunto.
 */
final class ProfileService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Catálogo de perfiles disponibles con su etiqueta y capacidades (permisos que habilita).
     * @return array<int, array{value:string,label:string,capabilities:array<int,string>}>
     */
    public function catalog(): array
    {
        return array_map(static fn (OperativeProfile $p): array => [
            'value'        => $p->value,
            'label'        => $p->label(),
            'capabilities' => $p->capabilities(),
        ], OperativeProfile::cases());
    }

    /**
     * Reemplaza el conjunto de perfiles de un usuario (idempotente y atómico). assigned_by = actor.
     * @param array<int, string> $profiles
     */
    public function replace(User $target, array $profiles, User $actor): User
    {
        DB::transaction(function () use ($target, $profiles, $actor): void {
            $desired = array_values(array_unique($profiles));

            $target->operativeProfiles()->delete();

            foreach ($desired as $profile) {
                $row = new UserOperativeProfile();
                $row->user_id     = $target->id;
                $row->profile     = $profile;
                $row->assigned_by = $actor->id;
                $row->business_id = $target->business_id; // aislamiento explícito (target y actor comparten negocio).
                $row->save();
            }

            $this->audit->record('profiles_changed', $target, actor: $actor, new: ['profiles' => $desired]);
        });

        return $target->load('operativeProfiles');
    }

    /** Limpia todos los perfiles (p. ej. al dejar de ser ROL-03). No audita si no había ninguno. */
    public function clear(User $target): void
    {
        if ($target->operativeProfiles()->exists()) {
            $target->operativeProfiles()->delete();
        }
    }
}
