<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SalesReturn;

use App\Enums\CreditNoteResolutionType;
use App\Enums\CreditNoteStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Validation\Rule;

final class IndexCreditNoteRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true; // CreditNotePolicy::viewAny.
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }

    /**
     * Allowlist de ordenamiento (H-04). Obligatorio: HasPaginationRules lo declara abstracto.
     *
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'folio', 'issued_at', 'status', 'total_amount', 'created_at'];
    }

    public function rules(): array
    {
        return array_merge(
            [
                // invoices NO es soft-deletable ⇒ excludeTrashed:false (evita 42S22 → 500).
                'invoice_id'      => ['sometimes', 'integer', $this->tenantExists('invoices', 'id', excludeTrashed: false)],
                'customer_id'     => ['sometimes', 'integer', $this->tenantExists('customers')], // soft-deletable
                'resolution_type' => ['sometimes', 'string', Rule::in(CreditNoteResolutionType::values())],
                'status'          => ['sometimes', 'string', Rule::in(CreditNoteStatus::values())],
            ],
            $this->paginationRules(),
        );
    }

    public function messages(): array
    {
        return $this->paginationMessages();
    }

    public function attributes(): array
    {
        return array_merge(
            $this->paginationAttributes(),
            [
                'invoice_id'      => 'factura',
                'customer_id'     => 'cliente',
                'resolution_type' => 'tipo de resolución',
                'status'          => 'estado',
            ],
        );
    }
}
