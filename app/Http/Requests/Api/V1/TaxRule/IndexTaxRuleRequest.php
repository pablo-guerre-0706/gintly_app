<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\TaxRule;

use App\Enums\TaxClass;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\TaxRule;
use Illuminate\Validation\Rule;

final class IndexTaxRuleRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TaxRule::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['tax_class', 'rate', 'is_active', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'tax_class' => ['sometimes', Rule::enum(TaxClass::class)],
            'branch_id' => ['sometimes', 'integer', $this->tenantExists('branches')],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'tax_class.enum'  => 'La clase fiscal indicada no es válida.',
            'branch_id.exists' => 'La sucursal indicada no existe o no pertenece a su negocio.',
            'is_active.boolean' => 'El filtro de estado debe ser verdadero o falso.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'tax_class' => 'clase fiscal',
            'branch_id' => 'sucursal',
            'is_active' => 'estado',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
