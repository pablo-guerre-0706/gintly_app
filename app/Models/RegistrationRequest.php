<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de idempotencia del alta pública (POST /api/v1/auth/register).
 *
 * INFRAESTRUCTURA pre-tenant: NO usa BelongsToBusiness ni BusinessScope, porque debe consultarse ANTES de
 * que exista un tenant autenticado (el visitante no tiene sesión ni business_id). La unicidad de `uuid`
 * (candado de motor) es el árbitro definitivo de concurrencia. El `fingerprint` (HMAC-SHA256) distingue
 * payloads bajo la misma clave; la contraseña entra en su cálculo pero NUNCA se persiste aquí.
 */
final class RegistrationRequest extends Model
{
    protected $fillable = [
        'uuid',
        'fingerprint',
        'business_id',
        'business_slug',
        'owner_email',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Resultado público persistido (idéntico al cuerpo del endpoint).
     *
     * @return array{business_slug: string, owner_email: string}
     */
    public function publicResult(): array
    {
        return [
            'business_slug' => (string) $this->business_slug,
            'owner_email'   => (string) $this->owner_email,
        ];
    }
}
