<?php

declare(strict_types=1);

namespace App\Enums;


// Tipo de documento de identidad del cliente (customers.document_type).
// `generico` es exclusivo del singleton "Consumidor Final"
enum DocumentType: string
{
    case Cedula    = 'cedula';
    case Ruc       = 'ruc';
    case Pasaporte = 'pasaporte';
    case Generico  = 'generico';

    public function label(): string
    {
        return match ($this) {
            self::Cedula    => 'Cédula',
            self::Ruc       => 'RUC',
            self::Pasaporte => 'Pasaporte',
            self::Generico  => 'Genérico',
        };
    }

    // True si el tipo corresponde al cliente genérico del sistema.
    public function isGeneric(): bool
    {
        return $this === self::Generico;
    }

    /**
     * Valores admisibles para creación por API: excluye 'generico'.
     *
     * @return array<int, string>
     */
    public static function publicValues(): array
    {
        return [
            self::Cedula->value,
            self::Ruc->value,
            self::Pasaporte->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

