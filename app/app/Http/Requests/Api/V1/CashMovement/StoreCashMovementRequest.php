<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\CashMovement;

use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Http\Requests\BaseTenantRequest;
use App\Models\CashMovement;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;


final class StoreCashMovementRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashMovement::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cash_session_id' => ['required', 'integer', $this->tenantExists('cash_sessions', 'id', excludeTrashed: false)],

            'type'           => ['required', Rule::enum(CashMovementType::class)],
            'category'       => ['required', Rule::enum(CashMovementCategory::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],

            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],

            // authorized_by: existe y es del tenant. Que sea ROL-02 lo valida el service
            'authorized_by' => [
                'nullable',
                'integer',
                'required_if:category,'.CashMovementCategory::EgresoAutorizado->value,
                $this->tenantExists('users', 'id', excludeTrashed: true),
            ],

            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * D-22 · Coherencia type⇄category vía forcedType() del enum. Una venta es
     * siempre ingreso; un retiro siempre egreso. El ajuste no fuerza type.
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $categoryValue = $this->input('category');
                $typeValue = $this->input('type');

                $category = CashMovementCategory::tryFrom((string) $categoryValue);
                $type = CashMovementType::tryFrom((string) $typeValue);

                if ($category === null || $type === null) {
                    return; // los enum rules ya reportan el valor inválido
                }

                $forced = $category->forcedType();

                if ($forced !== null && $forced !== $type) {
                    $validator->errors()->add(
                        'type',
                        "La categoría \"{$category->label()}\" exige un movimiento de tipo \"{$forced->label()}\"."
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cash_session_id.required' => 'La sesión de caja es obligatoria.',
            'cash_session_id.exists'   => 'La sesión indicada no existe o no pertenece a su negocio.',
            'type.required'            => 'El tipo de movimiento es obligatorio.',
            'type.enum'                => 'El tipo de movimiento debe ser ingreso o egreso.',
            'category.required'        => 'La categoría del movimiento es obligatoria.',
            'category.enum'            => 'La categoría indicada no es válida.',
            'payment_method.required'  => 'El medio de pago es obligatorio.',
            'payment_method.enum'      => 'El medio de pago debe ser efectivo, transferencia o tarjeta.',
            'amount.required'          => 'El monto es obligatorio.',
            'amount.decimal'           => 'El monto admite un máximo de dos decimales.',
            'amount.gt'                => 'El monto debe ser mayor que cero.',
            'authorized_by.required_if' => 'Un egreso autorizado exige registrar quién lo autoriza.',
            'authorized_by.exists'     => 'El autorizante indicado no existe o no pertenece a su negocio.',
            'description.max'          => 'La descripción no puede exceder los 255 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cash_session_id' => 'sesión de caja',
            'type'            => 'tipo',
            'category'        => 'categoría',
            'payment_method'  => 'medio de pago',
            'amount'          => 'monto',
            'authorized_by'   => 'autorizante',
            'description'     => 'descripción',
        ];
    }
}

