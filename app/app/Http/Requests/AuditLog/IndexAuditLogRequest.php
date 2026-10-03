<?php

declare(strict_types=1);

namespace App\Http\Requests\AuditLog;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\AuditLog;
use Illuminate\Validation\Rule;

/**
 * Recurso de SOLO lectura (RF-01-03). Sin Store ni Update por diseño:
 * cualquier escritura la rechaza el propio modelo con InmutableAuditException.
 */
final class IndexAuditLogRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    /**
     * Tabla append-only sin `updated_at`: el único orden con sentido es el
     * cronológico de inserción.
     *
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            $this->paginationRules(),
            $this->dateRangeRules(),
            [
                'user_id' => [
                    'sometimes',
                    'integer',
                    $this->tenantExists('users'),
                ],

                // Allowlist: sin ella el parámetro es texto libre en un WHERE
                // y permite enumerar acciones internas por tanteo.
                'action' => [
                    'sometimes',
                    'string',
                    'max:100',
                    Rule::in((array) config('gintly.audit.actions', [])),
                ],

                'auditable_type' => [
                    'sometimes',
                    'string',
                    'max:120',
                    Rule::in((array) config('gintly.audit.auditable_types', [])),
                ],

                'auditable_id' => ['sometimes', 'integer', 'min:1', 'required_with:auditable_type'],

                'ip_address' => ['sometimes', 'string', 'ip'],
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            $this->paginationMessages(),
            $this->dateRangeMessages(),
            [
                'user_id.integer'             => 'El usuario indicado en el filtro no es válido.',
                'user_id.exists'              => 'El usuario indicado en el filtro no existe o no pertenece a su negocio.',
                'action.in'                   => 'La acción indicada no corresponde a ninguna acción registrable del sistema.',
                'action.max'                  => 'La acción no puede exceder los 100 caracteres.',
                'auditable_type.in'           => 'El tipo de entidad indicado no es auditable en el sistema.',
                'auditable_type.max'          => 'El tipo de entidad no puede exceder los 120 caracteres.',
                'auditable_id.integer'        => 'El identificador de la entidad debe ser un valor entero.',
                'auditable_id.min'            => 'El identificador de la entidad debe ser mayor que cero.',
                'auditable_id.required_with'  => 'Debe indicar el tipo de entidad junto con su identificador.',
                'ip_address.ip'               => 'La dirección de origen debe ser una IP válida (IPv4 o IPv6).',
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(
            $this->paginationAttributes(),
            $this->dateRangeAttributes(),
            [
                'user_id'        => 'usuario',
                'action'         => 'acción',
                'auditable_type' => 'tipo de entidad',
                'auditable_id'   => 'identificador de la entidad',
                'ip_address'     => 'dirección de origen',
            ]
        );
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
