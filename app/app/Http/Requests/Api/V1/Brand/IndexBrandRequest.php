<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Brand;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\Brand;

final class IndexBrandRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Brand::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'is_active', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'is_active' => ['sometimes', 'boolean'],
            'search'    => ['sometimes', 'string', 'min:2', 'max:120'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'is_active.boolean' => 'El filtro de estado debe ser verdadero o falso.',
            'search.min'        => 'El término de búsqueda debe tener al menos 2 caracteres.',
            'search.max'        => 'El término de búsqueda no puede exceder los 120 caracteres.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'is_active' => 'estado',
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
