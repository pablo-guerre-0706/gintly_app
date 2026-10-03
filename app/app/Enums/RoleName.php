<?php

declare(strict_types=1);

namespace App\Enums;


// Centraliza los nombres de los roles en un solo lugar y aclara que las tareas 
// automáticas del sistema no necesitan permisos.
enum RoleName: string
{
    case System   = 'ROL-SYS'; 
    case Owner    = 'ROL-01';
    case Admin    = 'ROL-02';
    case Operator = 'ROL-03';

    public function label(): string
    {
        return match ($this) {
            self::System   => 'Soporte Sistema', 
            self::Owner    => 'Propietario',
            self::Admin    => 'Administrador',
            self::Operator => 'Empleado operativo',
        };
    }

    // Ordena los roles por niveles de poder para simplificar las reglas de permisos en el código
    public function level(): int
    {
        return match ($this) {
            self::System   => 4, 
            self::Owner    => 3,
            self::Admin    => 2,
            self::Operator => 1,
        };
    }

    public function atLeast(self $minimum): bool
    {
        // ROL-SYS queda FUERA de la jerarquía humana de autorización: aunque su level() sea el
        // más alto (para las comparaciones de rango internas), jamás satisface una compuerta de
        // rol humano (hasAtLeast/holdsAtLeast). Así, una sesión ROL-SYS ya emitida no "aprueba"
        // ninguna Policy humana ni siquiera si el middleware fallara (defensa en profundidad).
        if ($this->isSystem() || $minimum->isSystem()) {
            return false;
        }

        return $this->level() >= $minimum->level();
    }

    /** ROL-SYS es un actor de procesos automáticos: nunca un usuario humano asignable ni con sesión. */
    public function isSystem(): bool
    {
        return $this === self::System;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Roles HUMANOS (excluye ROL-SYS). Base del catálogo de roles asignables.
     * @return array<int, self>
     */
    public static function humanCases(): array
    {
        return [self::Owner, self::Admin, self::Operator];
    }

    /**
     * Valores de roles humanos asignables por la API. NUNCA incluye ROL-SYS.
     * @return array<int, string>
     */
    public static function assignableValues(): array
    {
        return array_map(static fn (self $r): string => $r->value, self::humanCases());
    }

    /**
     * Roles que un actor con este rol puede conceder (regla de rango, defensa en profundidad):
     * solo roles HUMANOS de nivel menor o igual al suyo. ROL-SYS jamás es concedible.
     * @return array<int, string>
     */
    public function grantableValues(): array
    {
        if ($this->isSystem()) {
            return []; // ROL-SYS no es un actor humano; no concede roles por la API.
        }

        return array_values(array_filter(
            self::assignableValues(),
            fn (string $value): bool => self::from($value)->level() <= $this->level(),
        ));
    }
}
