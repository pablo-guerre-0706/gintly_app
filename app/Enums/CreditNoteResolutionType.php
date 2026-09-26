<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditNoteResolutionType: string
{
    case ReembolsoEfectivo = 'reembolso_efectivo';
    case NotaCreditoSaldo  = 'nota_credito_saldo';
    case ReduccionCxc      = 'reduccion_cxc';
    // Cabecera de una NC cuyo resarcimiento se aplicó por DOS vías distintas (p. ej. reducir la
    // CxC por el saldo pendiente y devolver el excedente ya pagado). El desglose real y trazable
    // vive en credit_note_resolutions. 'mixto' NUNCA es una vía de aplicación individual.
    case Mixto             = 'mixto';

    /** @return array<int, string> Todos los valores (incluye la cabecera 'mixto'). */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    /**
     * Vías de aplicación CONCRETAS (excluye la cabecera 'mixto'): son los únicos valores válidos
     * en credit_note_resolutions.resolution_type.
     *
     * @return array<int, string>
     */
    public static function applicableValues(): array
    {
        return [
            self::ReembolsoEfectivo->value,
            self::NotaCreditoSaldo->value,
            self::ReduccionCxc->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::ReembolsoEfectivo => 'Reembolso en efectivo',
            self::NotaCreditoSaldo  => 'Nota de crédito (saldo a favor)',
            self::ReduccionCxc      => 'Reducción de cuenta por cobrar',
            self::Mixto             => 'Resarcimiento mixto',
        };
    }
}
