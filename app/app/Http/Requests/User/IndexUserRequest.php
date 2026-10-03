<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\User;

final class IndexUserRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'email', 'is_active', 'last_login_at', 'created_at'];
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

                'branch_id' => [
                    'sometimes',
                    'integer',
                    $this->tenantExists('branches'),
                ],

                // Cota inferior: un LIKE '%a%' sobre la tabla completa es un
                // recorrido secuencial disfrazado de búsqueda.
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
                'is_active.boolean' => 'El filtro de estado debe ser verdadero o falso.',
                'branch_id.integer' => 'La sucursal indicada en el filtro no es válida.',
                'branch_id.exists'  => 'La sucursal indicada en el filtro no existe o no pertenece a su negocio.',
                'search.min'        => 'El término de búsqueda debe tener al menos 2 caracteres.',
                'search.max'        => 'El término de búsqueda no puede exceder los 150 caracteres.',
                'trashed.in'        => 'El filtro de registros dados de baja admite únicamente los valores "with" u "only".',
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
                'is_active' => 'estado',
                'branch_id' => 'sucursal',
                'search'    => 'término de búsqueda',
                'trashed'   => 'registros dados de baja',
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
