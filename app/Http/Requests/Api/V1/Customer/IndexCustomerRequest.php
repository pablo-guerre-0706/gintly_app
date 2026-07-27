<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\DocumentType;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\Customer;
use Illuminate\Validation\Rule;

final class IndexCustomerRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'document_number', 'is_active', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'search'        => ['sometimes', 'string', 'min:2', 'max:160'],
            // Solo los tres tipos públicos en el filtro (D-16).
            'document_type' => ['sometimes', Rule::in(DocumentType::publicValues())],
            'is_active'     => ['sometimes', 'boolean'],
            // H-45 · por defecto excluye el genérico; true lo incluye.
            'include_generic' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'search.min'          => 'El término de búsqueda debe tener al menos 2 caracteres.',
            'search.max'          => 'El término de búsqueda no puede exceder los 160 caracteres.',
            'document_type.in'    => 'El tipo de documento indicado no es válido.',
            'is_active.boolean'   => 'El filtro de estado debe ser verdadero o falso.',
            'include_generic.boolean' => 'El indicador de cliente genérico debe ser verdadero o falso.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'search'          => 'término de búsqueda',
            'document_type'   => 'tipo de documento',
            'is_active'       => 'estado',
            'include_generic' => 'incluir genérico',
        ]);
    }

    // True si el listado debe incluir el Consumidor Final.
    // El controlador aplica scopeReal() salvo que esto sea true.
    public function includesGeneric(): bool
    {
        return $this->boolean('include_generic');
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();

        if (is_string($this->search)) {
            $this->merge(['search' => trim($this->search)]);
        }
    }
}
