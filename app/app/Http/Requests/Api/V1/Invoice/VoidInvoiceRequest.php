<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Invoice;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Invoice;

/**
 * Anulación por ROL-01. void_reason obligatorio: una anulación sin motivo 
 * registrado es indefendible en auditoría.
 */
final class VoidInvoiceRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('invoice');

        return $target instanceof Invoice
            && ($this->user()?->can('void', $target) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'void_reason.required' => 'El motivo de anulación es obligatorio.',
            'void_reason.min'      => 'El motivo debe tener al menos 3 caracteres.',
            'void_reason.max'      => 'El motivo no puede exceder los 255 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['void_reason' => 'motivo de anulación'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->void_reason)) {
            $this->merge(['void_reason' => trim($this->void_reason)]);
        }
    }
}
