<?php

namespace App\Enums;

enum CashMovementCategory: string
{
    case Venta            = 'venta';
    case EgresoAutorizado = 'egreso_autorizado';
    case Retiro           = 'retiro';
    case Ajuste           = 'ajuste';
    case FondoInicial     = 'fondo_inicial';
    case CobroCredito     = 'cobro_credito';   // requerido por MOD-08

    /** El fondo inicial ya está en opening_amount: se excluye del esperado (evita doble conteo). */
    public function countsInExpected(): bool
    {
        return $this !== self::FondoInicial;
    }

    public function requiresAuthorization(): bool
    {
        return $this === self::EgresoAutorizado;
    }

    /** Tipo forzado por la categoría (null = ajuste, admite ambos). Coherencia de dominio (#5). */
    public function forcedType(): ?CashMovementType
    {
        return match ($this) {
            self::Venta, self::FondoInicial, self::CobroCredito => CashMovementType::Ingreso,
            self::EgresoAutorizado, self::Retiro                => CashMovementType::Egreso,
            self::Ajuste                                        => null,
        };
    }
}
