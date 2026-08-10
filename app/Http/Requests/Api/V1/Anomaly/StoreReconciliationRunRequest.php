<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Anomaly;

use App\Enums\ReconciliationScope;
use App\Http\Requests\BaseTenantRequest;
use Illuminate\Validation\Rule;

final class StoreReconciliationRunRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // ReconciliationRunPolicy::create.
    }

    public function rules(): array
    {
        return [
            'scope'     => ['required', 'string', Rule::in(ReconciliationScope::values())],
            'branch_id' => ['nullable', 'integer', $this->tenantExists('branches')],
        ];
    }

    public function attributes(): array
    {
        return ['scope' => 'alcance', 'branch_id' => 'sucursal'];
    }
}
