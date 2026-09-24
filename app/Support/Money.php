<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Aritmética monetaria determinista con BCMath. NUNCA float.
 *
 * bcmul/bcadd TRUNCAN a la escala dada; el impuesto fiscal exige redondeo
 * determinista (mitad hacia arriba, simétrico respecto de cero) a dos decimales.
 * Este helper centraliza esa política para que la agregación sea reproducible.
 */
final class Money
{
    public const SCALE = 2;

    /** Escala interna para multiplicar tasa (6 decimales) × base (2). */
    public const CALC_SCALE = 8;

    /**
     * Redondeo monetario determinista (half-up, away from zero) a `$scale` decimales.
     */
    public static function round(string $value, int $scale = self::SCALE): string
    {
        $negative = bccomp($value, '0', self::CALC_SCALE) < 0;
        $magnitude = $negative ? substr($value, 1) : $value;

        // Sumar medio en la posición $scale y truncar a $scale = mitad hacia arriba.
        $half = '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd($magnitude, $half, $scale);

        return $negative && bccomp($rounded, '0', $scale) !== 0
            ? '-'.$rounded
            : $rounded;
    }

    /**
     * Impuesto de una base gravable a una tasa fraccional, redondeado a 2 decimales.
     */
    public static function tax(string $base, string $rate): string
    {
        return self::round(bcmul($base, $rate, self::CALC_SCALE), self::SCALE);
    }
}
