<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Categoría del movimiento de caja (cash_movements.category).
 */
enum CashMovementCategory: string
{
    case Venta            = 'venta';
    case EgresoAutorizado = 'egreso_autorizado';
    case Retiro           = 'retiro';
    case Ajuste           = 'ajuste';
    case FondoInicial     = 'fondo_inicial';
    case CobroCredito     = 'cobro_credito';

    public function label(): string
    {
        return match ($this) {
            self::Venta            => 'Venta',
            self::EgresoAutorizado => 'Egreso autorizado',
            self::Retiro           => 'Retiro',
            self::Ajuste           => 'Ajuste',
            self::FondoInicial     => 'Fondo inicial',
            self::CobroCredito     => 'Cobro de crédito',
        };
    }

    // Type obligatorio para esta categoría.
    public function forcedType(): ?CashMovementType
    {
        return match ($this) {
            self::Venta, self::CobroCredito, self::FondoInicial => CashMovementType::Ingreso,
            self::EgresoAutorizado, self::Retiro               => CashMovementType::Egreso,
            self::Ajuste                                        => null,
        };
    }

    // Solo el egreso autorizado exige registro del autorizante (ROL-02).
    public function requiresAuthorization(): bool
    {
        return $this === self::EgresoAutorizado;
    }

    public function countsInExpected(): bool
    {
        return $this !== self::FondoInicial;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
