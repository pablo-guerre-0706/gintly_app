<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\ExchangeRate;

use App\Enums\Currency;
use App\Http\Requests\BaseTenantRequest;
use App\Models\ExchangeRate;
use Illuminate\Validation\Rule;

/**
 * MOD-06 · Alta de una vigencia de tipo de cambio (ROL-01/ROL-02). La moneda base (NIO) se rechaza:
 * su tasa es 1 por definición y no se administra. La tasa es NIO por 1 unidad de la moneda extranjera,
 * con hasta 6 decimales y estrictamente positiva.
 */
final class StoreExchangeRateRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExchangeRate::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Moneda admitida pero NUNCA la base: NIO no tiene tipo de cambio.
            'currency'       => ['required', Rule::enum(Currency::class), Rule::notIn([Currency::base()->value])],
            // NIO por 1 unidad de la moneda extranjera. > 0, hasta 6 decimales.
            'rate'           => ['required', 'numeric', 'decimal:0,6', 'gt:0'],
            // Vigencia desde: fecha/hora a partir de la cual la tasa aplica.
            'effective_from' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency.required'       => 'La moneda es obligatoria.',
            'currency.enum'           => 'La moneda indicada no es admitida.',
            'currency.not_in'         => 'La moneda base (NIO) no admite tipo de cambio: su tasa es 1.',
            'rate.required'           => 'El tipo de cambio es obligatorio.',
            'rate.numeric'            => 'El tipo de cambio debe ser un valor numérico.',
            'rate.decimal'            => 'El tipo de cambio admite un máximo de seis decimales.',
            'rate.gt'                 => 'El tipo de cambio debe ser mayor que cero.',
            'effective_from.required' => 'La fecha de vigencia es obligatoria.',
            'effective_from.date'     => 'La fecha de vigencia no es válida.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'currency'       => 'moneda',
            'rate'           => 'tipo de cambio',
            'effective_from' => 'fecha de vigencia',
        ];
    }
}
