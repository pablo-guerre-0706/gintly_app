<?php

declare(strict_types=1);

namespace App\Services\Cash;

use App\Enums\Currency;
use App\Exceptions\ExchangeRateMissingException;
use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * MOD-06 · Resolución y administración del tipo de cambio (doble moneda NIO/USD).
 *
 * La moneda base (NIO) NO se almacena: su tasa es 1 por definición. Para una moneda extranjera, la tasa
 * VIGENTE en un instante es la fila de mayor `effective_from <= $at` para (negocio, moneda); si no hay
 * ninguna, se rechaza de forma CONTROLADA (ExchangeRateMissingException, 422): una operación en moneda
 * extranjera no puede asentarse sin una tasa snapshot administrada. El historial es inmutable y
 * append-only; `register()` solo INSERTA una nueva vigencia, nunca muta las anteriores.
 */
final class ExchangeRateService
{
    private const RATE_SCALE = 6;

    /**
     * Tasa (NIO por 1 unidad de `currency`) vigente en `$at` (por defecto, ahora). Base → '1'.
     * El resultado es la tasa que se CONGELA como snapshot en la operación que la solicita.
     */
    public function rateFor(int $businessId, Currency $currency, ?Carbon $at = null): string
    {
        if ($currency->isBase()) {
            return '1'; // NIO: tasa implícita; no vive en exchange_rates.
        }

        $at ??= Carbon::now();

        $rate = ExchangeRate::query()
            ->where('business_id', $businessId)
            ->where('currency', $currency->value)
            ->where('effective_from', '<=', $at)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->value('rate');

        if ($rate === null) {
            throw ExchangeRateMissingException::forCurrency($currency);
        }

        return (string) $rate;
    }

    /**
     * Registra una nueva vigencia (ROL-01/ROL-02). Append-only: cada llamada INSERTA una fila; las
     * anteriores se preservan intactas (inmutabilidad histórica). La moneda base nunca se almacena.
     */
    public function register(
        int $businessId,
        Currency $currency,
        string $rate,
        Carbon $effectiveFrom,
        int $createdBy
    ): ExchangeRate {
        if ($currency->isBase()) {
            // Defensa en profundidad: el FormRequest y el CHECK de motor ya lo impiden.
            throw new InvalidArgumentException('La moneda base (NIO) no admite tipo de cambio: su tasa es 1.');
        }

        if (bccomp($rate, '0', self::RATE_SCALE) <= 0) {
            throw new InvalidArgumentException('El tipo de cambio debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($businessId, $currency, $rate, $effectiveFrom, $createdBy): ExchangeRate {
            $row = new ExchangeRate();
            $row->forceFill([
                'business_id'    => $businessId,
                'currency'       => $currency->value,
                'rate'           => $rate,
                'effective_from' => $effectiveFrom,
                'created_by'     => $createdBy,
            ])->save();

            return $row->refresh();
        });
    }
}
