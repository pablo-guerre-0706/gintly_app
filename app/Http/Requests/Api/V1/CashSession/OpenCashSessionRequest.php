<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\CashSession;

use App\Http\Requests\BaseTenantRequest;
use App\Models\CashSession;

/**
 * Apertura con fondo inicial.
 */
final class OpenCashSessionRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('open', CashSession::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cash_register_id' => ['required', 'integer', $this->tenantExists('cash_registers')->where('is_active', true)],

            // Fondo inicial obligatorio, >= 0 (RF-06-03). Escala 2.
            'opening_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cash_register_id.required' => 'La caja es obligatoria.',
            'cash_register_id.exists'   => 'La caja no existe, está inactiva o no pertenece a su negocio.',
            'opening_amount.required'   => 'El fondo inicial es obligatorio.',
            'opening_amount.numeric'    => 'El fondo inicial debe ser un valor numérico.',
            'opening_amount.decimal'    => 'El fondo inicial admite un máximo de dos decimales.',
            'opening_amount.min'        => 'El fondo inicial no puede ser negativo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cash_register_id' => 'caja',
            'opening_amount'   => 'fondo inicial',
        ];
    }
}
