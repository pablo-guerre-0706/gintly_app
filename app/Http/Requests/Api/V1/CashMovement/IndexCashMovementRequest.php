<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\CashMovement;

use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\CashMovement;
use Illuminate\Validation\Rule;

final class IndexCashMovementRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CashMovement::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['created_at', 'amount', 'type', 'category'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'cash_session_id' => ['sometimes', 'integer', $this->tenantExists('cash_sessions', 'id', excludeTrashed: false)],
            'type'            => ['sometimes', Rule::enum(CashMovementType::class)],
            'category'        => ['sometimes', Rule::enum(CashMovementCategory::class)],
            'payment_method'  => ['sometimes', Rule::enum(PaymentMethod::class)],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'cash_session_id.exists' => 'La sesión indicada no existe o no pertenece a su negocio.',
            'type.enum'              => 'El tipo indicado no es válido.',
            'category.enum'          => 'La categoría indicada no es válida.',
            'payment_method.enum'    => 'El medio de pago indicado no es válido.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'cash_session_id' => 'sesión de caja',
            'type'            => 'tipo',
            'category'        => 'categoría',
            'payment_method'  => 'medio de pago',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
