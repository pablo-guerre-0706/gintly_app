<?php

namespace App\Enums;

enum DocumentType: string
{
    case Cedula    = 'cedula';
    case Ruc       = 'ruc';
    case Pasaporte = 'pasaporte';
    case Generico  = 'generico';   // exclusivo del Consumidor Final

    public function label(): string
    {
        return match ($this) {
            self::Cedula    => 'Cédula',
            self::Ruc       => 'RUC',
            self::Pasaporte => 'Pasaporte',
            self::Generico  => 'Genérico',
        };
    }
}
