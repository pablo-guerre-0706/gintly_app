<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\CashSession;

use App\Http\Requests\BaseTenantRequest;
use App\Models\CashSession;
use Illuminate\Validation\Validator;

/**
 * Arqueo ciego. counted_amount y el desglose son obligatorios.
 */
final class CloseCashSessionRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('cash_session');

        return $target instanceof CashSession
            && ($this->user()?->can('close', $target) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'counted_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],

            // Desglose obligatorio y no vacío (evidencia del arqueo).
            'counted_denominations'          => ['required', 'array', 'min:1'],
            'counted_denominations.*.value'  => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'counted_denominations.*.qty'    => ['required', 'integer', 'min:0'],

            'closing_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * La suma del desglose debe igualar exactamente counted_amount.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $denominations = $this->input('counted_denominations');

                if (! is_array($denominations) || $denominations === []) {
                    return; // ya lo cubre la regla 'required|array|min:1'
                }

                $sum = '0.00';
                foreach ($denominations as $line) {
                    if (! isset($line['value'], $line['qty'])) {
                        continue;
                    }
                    // value(2) × qty(entero) acumulado en escala 2.
                    $lineTotal = bcmul((string) $line['value'], (string) (int) $line['qty'], 2);
                    $sum = bcadd($sum, $lineTotal, 2);
                }

                $counted = (string) $this->input('counted_amount');

                if (bccomp($sum, $counted, 2) !== 0) {
                    $validator->errors()->add(
                        'counted_denominations',
                        "El desglose de denominaciones (suma {$sum}) no coincide con el efectivo declarado ({$counted})."
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
            'counted_amount.required'          => 'El efectivo contado es obligatorio.',
            'counted_amount.numeric'           => 'El efectivo contado debe ser un valor numérico.',
            'counted_amount.decimal'           => 'El efectivo contado admite un máximo de dos decimales.',
            'counted_amount.min'               => 'El efectivo contado no puede ser negativo.',
            'counted_denominations.required'   => 'El desglose de denominaciones es obligatorio para cerrar la caja.',
            'counted_denominations.min'        => 'Debe registrar al menos una denominación.',
            'counted_denominations.*.value.required' => 'Cada denominación debe indicar su valor.',
            'counted_denominations.*.value.gt' => 'El valor de la denominación debe ser mayor que cero.',
            'counted_denominations.*.qty.required'   => 'Cada denominación debe indicar su cantidad.',
            'counted_denominations.*.qty.integer'    => 'La cantidad de cada denominación debe ser un número entero.',
            'counted_denominations.*.qty.min'        => 'La cantidad de cada denominación no puede ser negativa.',
            'closing_notes.max'                => 'Las observaciones no pueden exceder los 500 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'counted_amount'        => 'efectivo contado',
            'counted_denominations' => 'desglose de denominaciones',
            'closing_notes'         => 'observaciones del cierre',
        ];
    }
}

