<?php

declare(strict_types=1);

namespace App\Enums;


// Centraliza los nombres de los roles en un solo lugar y aclara que las tareas 
// automáticas del sistema no necesitan permisos.
enum RoleName: string
{
    case Owner    = 'ROL-01';
    case Admin    = 'ROL-02';
    case Operator = 'ROL-03';

    public function label(): string
    {
        return match ($this) {
            self::Owner    => 'Propietario',
            self::Admin    => 'Administrador',
            self::Operator => 'Empleado operativo',
        };
    }

    // Ordena los roles por niveles de poder para simplificar las reglas de permisos en el código
    public function level(): int
    {
        return match ($this) {
            self::Owner    => 3,
            self::Admin    => 2,
            self::Operator => 1,
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->level() >= $minimum->level();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
