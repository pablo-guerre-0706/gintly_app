<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Perfiles operativos COMBINABLES de ROL-03 (Fase 3). ROL-03 sigue siendo un único rol; un usuario
 * puede tener uno o varios perfiles. El mapa perfil→capacidades vive en config/profiles.php (fuente
 * única, reutiliza el catálogo de permisos). ROL-01/ROL-02 no usan perfiles.
 */
enum OperativeProfile: string
{
    case Cajero      = 'cajero';
    case Facturador  = 'facturador';
    case Bodeguero   = 'bodeguero';
    case Despachador = 'despachador';

    public function label(): string
    {
        return match ($this) {
            self::Cajero      => 'Cajero',
            self::Facturador  => 'Facturador',
            self::Bodeguero   => 'Bodeguero',
            self::Despachador => 'Despachador',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Permisos (capacidades) que habilita este perfil, desde el registro canónico config/profiles.php.
     * @return array<int, string>
     */
    public function capabilities(): array
    {
        return (array) config("profiles.capabilities.{$this->value}", []);
    }

    /** ¿Este perfil habilita el permiso indicado? */
    public function grants(string $permission): bool
    {
        return in_array($permission, $this->capabilities(), true);
    }

    /**
     * Unión EXACTA de las capacidades de todos los perfiles (base de los permisos del rol ROL-03).
     * @return array<int, string>
     */
    public static function allCapabilities(): array
    {
        $all = [];
        foreach (self::cases() as $profile) {
            $all = array_merge($all, $profile->capabilities());
        }

        return array_values(array_unique($all));
    }
}
