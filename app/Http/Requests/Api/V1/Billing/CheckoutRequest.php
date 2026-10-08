<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contratación/cambio de plan. Requiere sesión Sanctum stateful + CSRF (aplicables por la configuración del
 * proyecto) y PROPIETARIO REAL activo con ROL-01 (owner_user_id coherente; no basta una indicación del
 * cliente). El negocio procede de la sesión. El cliente SOLO envía selectores plan y periodicidad; se
 * rechazan business_id, importes, estados, URLs y claves adicionales. Idempotency-Key UUID desde el header.
 */
final class CheckoutRequest extends FormRequest
{
    private const ALLOWED = ['plan', 'period'];

    /** @var array<string, mixed> */
    private array $rawBody = [];

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->is_active) {
            return false;
        }

        $business = $user->business;

        // Propietario REAL del negocio + ROL-01 (nivel Owner). Un ROL-02/03 no contrata.
        return $business !== null
            && (int) $business->owner_user_id === (int) $user->getKey()
            && $user->holdsAtLeast(RoleName::Owner);
    }

    public function rules(): array
    {
        return [
            'plan'            => ['required', 'string', Rule::in(array_keys((array) config('billing.catalog', [])))],
            'period'          => ['required', 'string', Rule::in((array) config('billing.periods', []))],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->rawBody = $this->all();

        $header = $this->header('Idempotency-Key');
        if (is_string($header)) {
            $this->merge(['idempotency_key' => mb_strtolower(trim($header))]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_keys($this->rawBody) as $key) {
                    if (! in_array($key, self::ALLOWED, true)) {
                        $validator->errors()->add((string) $key, "El campo {$key} no está permitido en la contratación.");
                    }
                }
            },
        ];
    }

    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotency_key');
    }

    public function messages(): array
    {
        return [
            'plan.required'            => 'Debe indicar el plan a contratar.',
            'plan.in'                  => 'El plan indicado no está disponible.',
            'period.required'          => 'Debe indicar la periodicidad (mensual o anual).',
            'period.in'                => 'La periodicidad indicada no es válida.',
            'idempotency_key.required' => 'El encabezado Idempotency-Key es obligatorio.',
            'idempotency_key.uuid'     => 'El encabezado Idempotency-Key debe ser un UUID válido.',
        ];
    }
}
