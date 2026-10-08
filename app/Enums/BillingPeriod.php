<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Carbon;

/** Periodicidad de contratación. Anual = 12 meses por adelantado, sin descuento. */
enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Annual  = 'annual';

    public function months(): int
    {
        return $this === self::Annual ? 12 : 1;
    }

    /** Extiende una base de vigencia por el período (base = max(ahora, vigencia previa) lo decide el servicio). */
    public function extend(Carbon $from): Carbon
    {
        return $from->copy()->addMonthsNoOverflow($this->months());
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
