<?php

declare(strict_types=1);

namespace App\Http\Requests\Branch;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\Branch;

final class IndexBranchRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Branch::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'opened_at', 'is_active', 'created_at'];
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
                'is_active' => ['sometimes', 'boolean'],

                'manager_user_id' => [
                    'sometimes',
                    'integer',
                    $this->tenantExists('users'),
                ],

                'search' => ['sometimes', 'string', 'min:2', 'max:150'],

                'trashed' => ['sometimes', 'string', 'in:with,only'],
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
                'is_active.boolean'       => 'El filtro de estado debe ser verdadero o falso.',
                'manager_user_id.integer' => 'El responsable indicado en el filtro no es válido.',
                'manager_user_id.exists'  => 'El responsable indicado en el filtro no existe o no pertenece a su negocio.',
                'search.min'              => 'El término de búsqueda debe tener al menos 2 caracteres.',
                'search.max'              => 'El término de búsqueda no puede exceder los 150 caracteres.',
                'trashed.in'              => 'El filtro de registros dados de baja admite únicamente los valores "with" u "only".',
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
                'is_active'       => 'estado',
                'manager_user_id' => 'responsable',
                'search'          => 'término de búsqueda',
                'trashed'         => 'registros dados de baja',
            ]
        );
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();

        if (is_string($this->search)) {
            $this->merge(['search' => trim($this->search)]);
        }
    }
}
