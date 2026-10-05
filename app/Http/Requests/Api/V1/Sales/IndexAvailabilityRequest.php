<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Sales;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\StockLevel;
use Illuminate\Validation\Validator;

/**
 * MOD-03 (microcierre) · Filtros de la consulta de disponibilidad para facturar. La autorización se delega a
 * StockLevelPolicy::viewForSelling (facturador o ROL-01/02, sin exigir inventario.ver). La sucursal se resuelve
 * del actor para ROL-03; ROL-01/ROL-02 deben indicarla (branch_id) porque no están acotados a una sucursal.
 */
final class IndexAvailabilityRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewForSelling', StockLevel::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['quantity', 'reserved_quantity', 'updated_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'branch_id'  => ['sometimes', 'integer', $this->tenantExists('branches')],
            'product_id' => ['sometimes', 'integer', $this->tenantExists('products')],
            'search'     => ['sometimes', 'string', 'min:2', 'max:160'],
        ]);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($user === null) {
                    return;
                }

                // ROL-01/ROL-02 no están acotados a una sucursal: deben indicar cuál consultar.
                if (! $user->isOperator() && $this->input('branch_id') === null) {
                    $validator->errors()->add('branch_id', 'Debe indicar la sucursal cuya disponibilidad desea consultar.');
                    return;
                }

                // ROL-03: la sucursal es la suya; no puede consultar otra.
                if ($user->isOperator()) {
                    if ($user->branch_id === null) {
                        $validator->errors()->add('branch_id', 'No tiene una sucursal asignada para consultar disponibilidad.');
                        return;
                    }

                    $requested = $this->input('branch_id');
                    if ($requested !== null && (int) $requested !== (int) $user->branch_id) {
                        $validator->errors()->add('branch_id', 'Solo puede consultar la disponibilidad de su sucursal.');
                    }
                }
            },
        ];
    }

    /**
     * Sucursal efectiva ya validada: la del actor para ROL-03, la indicada para ROL-01/ROL-02.
     */
    public function effectiveBranchId(): int
    {
        $user = $this->user();

        if ($user !== null && $user->isOperator()) {
            return (int) $user->branch_id;
        }

        return (int) $this->validated('branch_id');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'branch_id.integer'  => 'La sucursal indicada no es válida.',
            'branch_id.exists'   => 'La sucursal indicada no existe o no pertenece a su negocio.',
            'product_id.integer' => 'El producto indicado no es válido.',
            'product_id.exists'  => 'El producto indicado no existe o no pertenece a su negocio.',
            'search.min'         => 'El término de búsqueda debe tener al menos 2 caracteres.',
            'search.max'         => 'El término de búsqueda no puede exceder los 160 caracteres.',
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
