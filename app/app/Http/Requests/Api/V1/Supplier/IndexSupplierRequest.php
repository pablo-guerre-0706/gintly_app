<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Supplier;

use App\Enums\SupplierStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\Supplier;
use Illuminate\Validation\Rule;

final class IndexSupplierRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Supplier::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'status', 'is_active', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'status'    => ['sometimes', Rule::enum(SupplierStatus::class)],
            'is_active' => ['sometimes', 'boolean'],
            'search'    => ['sometimes', 'string', 'min:2', 'max:160'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'status.enum'       => 'El estado indicado no es válido (pendiente, aprobado o suspendido).',
            'is_active.boolean' => 'El filtro de estado debe ser verdadero o falso.',
            'search.min'        => 'El término de búsqueda debe tener al menos 2 caracteres.',
            'search.max'        => 'El término de búsqueda no puede exceder los 160 caracteres.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'status'    => 'estado',
            'is_active' => 'estado operativo',
            'search'    => 'término de búsqueda',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();

        if (is_string($this->search)) {
            $this->merge(['search' => trim($this->search)]);
        }
    }
}
