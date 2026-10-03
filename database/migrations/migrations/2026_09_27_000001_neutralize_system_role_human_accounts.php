<?php

declare(strict_types=1);

use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Reconciliación de seguridad (RF-01) · Neutraliza cuentas humanas con ROL-SYS.
 *
 * ROL-SYS representa procesos automáticos y NUNCA debe ser una cuenta iniciable. Toda cuenta
 * que conserve ROL-SYS se desactiva (is_active=0) y recibe una contraseña aleatoria inutilizable,
 * SIN eliminar el registro ni la asignación de rol (se preservan historial y atribución de auditoría).
 * La defensa primaria es AuthService (rechaza el login de ROL-SYS); esto es defensa en profundidad.
 *
 * Forward-only e idempotente: reejecutarlo sobre cuentas ya neutralizadas no causa daño.
 */
return new class extends Migration
{
    public function up(): void
    {
        $systemUserIds = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('r.name', RoleName::System->value)
            ->where('mhr.model_type', 'App\\Models\\User')
            ->pluck('mhr.model_id')
            ->unique()
            ->all();

        if ($systemUserIds === []) {
            return;
        }

        // Contraseña aleatoria e inutilizable por cuenta (no reversible, no adivinable).
        foreach ($systemUserIds as $id) {
            DB::table('users')->where('id', $id)->update([
                'is_active' => false,
                'password'  => Hash::make(Str::random(64)),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only por seguridad: no se restablece una contraseña conocida ni se reactiva
        // automáticamente una cuenta de sistema. Reactivar es una decisión administrativa explícita.
    }
};
