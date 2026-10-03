<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountReceivableStatus: string
{
    case Pendiente = 'pendiente';
    case Parcial   = 'parcial';
    case Pagada    = 'pagada';
    case Vencida   = 'vencida';

    /** Valores planos para reglas de validación y columnas ENUM del motor. */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    /**
     * Estados que un usuario SÍ puede provocar.
     * Excluye 'vencida', que es derivada del tiempo y solo la asigna el cron (RF-08-05).
     */
    public static function assignableValues(): array
    {
        return [self::Pendiente->value, self::Parcial->value, self::Pagada->value];
    }

    /** Estados cuyo saldo suma a la exposición del cliente (RF-08-02 / RF-08-06). */
    public static function exposureStatuses(): array
    {
        return [self::Pendiente->value, self::Parcial->value, self::Vencida->value];
    }

    /** ¿El saldo de esta cuenta pesa en el cupo del cliente? */
    public function countsInExposure(): bool
    {
        return in_array($this, [self::Pendiente, self::Parcial, self::Vencida], true);
    }

    /** ¿La cuenta está saldada (estado terminal)? */
    public function isSettled(): bool
    {
        return $this === self::Pagada;
    }

    /**
     * Deriva el estado NO vencido a partir de los montos (RF-08-04).
     * 'vencida' jamás se deriva aquí: es competencia exclusiva del cron (RF-08-05).
     * Todo comparado en bcmath escala 2 para no arrastrar imprecisión de float.
     */
    public static function fromAmounts(string $totalAmount, string $paidAmount): self
    {
        $balance = bcsub($totalAmount, $paidAmount, 2);

        if (bccomp($balance, '0.00', 2) <= 0) {
            return self::Pagada;
        }

        return bccomp($paidAmount, '0.00', 2) > 0 ? self::Parcial : self::Pendiente;
    }

public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Parcial   => 'Parcial',
            self::Pagada    => 'Pagada',
            self::Vencida   => 'Vencida',
        };
    }
}
