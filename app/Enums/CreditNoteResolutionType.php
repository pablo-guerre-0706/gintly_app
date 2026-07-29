<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditNoteResolutionType: string
{
    case ReembolsoEfectivo = 'reembolso_efectivo';
    case NotaCreditoSaldo  = 'nota_credito_saldo';
    case ReduccionCxc      = 'reduccion_cxc';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::ReembolsoEfectivo => 'Reembolso en efectivo',
            self::NotaCreditoSaldo  => 'Nota de crédito (saldo a favor)',
            self::ReduccionCxc      => 'Reducción de cuenta por cobrar',
        };
    }
}
