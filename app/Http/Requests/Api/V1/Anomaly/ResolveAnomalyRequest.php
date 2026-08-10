<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Anomaly;

use App\Http\Requests\BaseTenantRequest;

final class ResolveAnomalyRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return true; // AnomalyPolicy::resolve.
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('comment') && trim((string) $this->input('comment')) === '') {
            $this->merge(['comment' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['comment' => 'comentario'];
    }
}
