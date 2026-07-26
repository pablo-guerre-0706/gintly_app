<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\PhysicalCount;

use App\Http\Requests\BaseTenantRequest;
use App\Models\PhysicalCount;


// Exige un motivo: una diferencia justificada sin razón registrada no sirve a la
// auditoría ni al flujo de anomalías.
final class JustifyPhysicalCountRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('physical_count');

        return $target instanceof PhysicalCount
            && ($this->user()?->can('justify', $target) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Debe indicar el motivo que justifica la diferencia del conteo.',
            'reason.min'      => 'El motivo debe tener al menos 3 caracteres.',
            'reason.max'      => 'El motivo no puede exceder los 500 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => 'motivo'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->reason)) {
            $this->merge(['reason' => trim($this->reason)]);
        }
    }
}
