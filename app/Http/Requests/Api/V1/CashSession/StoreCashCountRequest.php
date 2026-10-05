<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\CashSession;

use App\Http\Requests\BaseTenantRequest;
use App\Models\CashSession;
use Illuminate\Validation\Validator;

/**
 * MOD-06 · Arqueo ciego independiente. counted_amount + desglose obligatorios (evidencia). El esperado
 * NO se envía ni se conoce al registrar (arqueo ciego): lo calcula el servidor y lo revela en la respuesta.
 */
final class StoreCashCountRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('cashSession');

        return $target instanceof CashSession
            && ($this->user()?->can('count', $target) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'counted_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'counted_denominations' => ['required', 'array', 'min:1'],
            'counted_denominations.*.value' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'counted_denominations.*.qty' => ['required', 'integer', 'min:0'],

            // Leg USD del arqueo ciego. Opcional (ausente ⇒ 0): arqueo NIO puro sin cambios.
            'counted_amount_usd' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'counted_denominations_usd' => ['sometimes', 'nullable', 'array'],
            'counted_denominations_usd.*.value' => ['required_with:counted_denominations_usd', 'numeric', 'decimal:0,2', 'gt:0'],
            'counted_denominations_usd.*.qty' => ['required_with:counted_denominations_usd', 'integer', 'min:0'],
        ];
    }

    /**
     * La suma del desglose debe igualar exactamente counted_amount (misma regla que el cierre).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $denominations = $this->input('counted_denominations');

                if (! is_array($denominations) || $denominations === []) {
                    return; // ya lo cubre 'required|array|min:1'
                }

                $sum = '0.00';
                foreach ($denominations as $line) {
                    if (! isset($line['value'], $line['qty'])) {
                        continue;
                    }
                    $sum = bcadd($sum, bcmul((string) $line['value'], (string) (int) $line['qty'], 2), 2);
                }

                $counted = (string) $this->input('counted_amount');

                if (bccomp($sum, $counted, 2) !== 0) {
                    $validator->errors()->add(
                        'counted_denominations',
                        "El desglose de denominaciones (suma {$sum}) no coincide con el efectivo declarado ({$counted})."
                    );
                }
            },
            // Igual regla para el desglose USD, solo si se declara un conteo en USD.
            function (Validator $validator): void {
                $denominations = $this->input('counted_denominations_usd');

                if (! is_array($denominations) || $denominations === []) {
                    return; // sin conteo USD: nada que validar.
                }

                $sum = '0.00';
                foreach ($denominations as $line) {
                    if (! isset($line['value'], $line['qty'])) {
                        continue;
                    }
                    $sum = bcadd($sum, bcmul((string) $line['value'], (string) (int) $line['qty'], 2), 2);
                }

                $counted = (string) ($this->input('counted_amount_usd') ?? '0.00');

                if (bccomp($sum, $counted, 2) !== 0) {
                    $validator->errors()->add(
                        'counted_denominations_usd',
                        "El desglose de denominaciones en USD (suma {$sum}) no coincide con el efectivo USD declarado ({$counted})."
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
            'counted_amount.required' => 'El efectivo contado es obligatorio.',
            'counted_amount.decimal' => 'El efectivo contado admite un máximo de dos decimales.',
            'counted_amount.min' => 'El efectivo contado no puede ser negativo.',
            'counted_denominations.required' => 'El desglose de denominaciones es obligatorio para arquear la caja.',
            'counted_denominations.min' => 'Debe registrar al menos una denominación.',
            'counted_denominations.*.value.gt' => 'El valor de la denominación debe ser mayor que cero.',
            'counted_denominations.*.qty.integer' => 'La cantidad de cada denominación debe ser un número entero.',
            'counted_denominations.*.qty.min' => 'La cantidad de cada denominación no puede ser negativa.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'counted_amount' => 'efectivo contado',
            'counted_denominations' => 'desglose de denominaciones',
        ];
    }
}
