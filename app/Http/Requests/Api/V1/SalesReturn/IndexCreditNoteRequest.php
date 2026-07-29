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

    public function rules(): array
    {
        return array_merge(
            [
                'invoice_id'      => ['sometimes', 'integer', $this->tenantExists('invoices')],
                'customer_id'     => ['sometimes', 'integer', $this->tenantExists('customers')],
                'resolution_type' => ['sometimes', 'string', Rule::in(CreditNoteResolutionType::values())],
                'status'          => ['sometimes', 'string', Rule::in(CreditNoteStatus::values())],
            ],
            $this->paginationRules(),
        );
    }

    public function attributes(): array
    {
        return [
            'invoice_id'      => 'factura',
            'customer_id'     => 'cliente',
            'resolution_type' => 'tipo de resolución',
            'status'          => 'estado',
        ];
    }
}
