<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Receivables;

use App\Enums\AccountReceivableStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Validation\Rule;

final class IndexAccountReceivableRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true; // La autorización la resuelve AccountReceivablePolicy::viewAny en el controlador.
    }

    public function rules(): array
    {
        return array_merge(
            [
                // tenantExists() acota el 'exists' al business_id del token (aislamiento).
                'customer_id' => ['sometimes', 'integer', $this->tenantExists('customers')],
                'status'      => ['sometimes', 'string', Rule::in(AccountReceivableStatus::values())],
                'overdue'     => ['sometimes', 'boolean'],
            ],
            $this->dateRangeRules(),   // from / to (trait)
            $this->paginationRules(),  // page / per_page (trait)
        );
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'cliente',
            'status'      => 'estado',
            'overdue'     => 'solo vencidas',
        ];
    }
}
