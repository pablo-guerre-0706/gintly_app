<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Base de toda petición ejecutada bajo sesión autenticada.
 * Centraliza el aislamiento multi-negocio: el identificador de
 * negocio se deriva SIEMPRE del token y nunca se acepta como dato de entrada.
 * Una sola omisión del filtro por negocio en un `exists` es una fuga de
 * tenant (ERR-12); por eso el filtro vive aquí y no en cada regla.
 */
abstract class BaseTenantRequest extends FormRequest
{
    /**
     * Negocio activo. Nunca se lee del cuerpo de la petición.
     */
    protected function businessId(): int
    {
        $user = $this->user() ?? throw new AuthenticationException();

        return (int) $user->business_id;
    }

    /**
     * Huso horario del negocio. Gobierna los cortes de periodo
     * de todo filtro por rango de fechas.
     */
    protected function businessTimezone(): string
    {
        return $this->user()?->business?->timezone
            ?? (string) config('app.timezone');
    }

    /**
     * La llave foránea garantiza EXISTENCIA; este helper añade PERTENENCIA.
     * Sin el filtro por negocio, un identificador de otro tenant superaría
     * la validación y el motor lo aceptaría sin objeción.
     *
     * @param  bool  $excludeTrashed  false en tablas sin columna `deleted_at`
     *                                (units_of_measure, roles), o el filtro
     *                                produce SQLSTATE 42S22.
     */
    protected function tenantExists(
        string $table,
        string $column = 'id',
        bool $excludeTrashed = true
    ): Exists {
        $rule = Rule::exists($table, $column)->where('business_id', $this->businessId());

        return $excludeTrashed ? $rule->whereNull('deleted_at') : $rule;
    }

    /**
     * Unicidad acotada al negocio, replicando el índice UQ(business_id, columna).
     * @param  bool  $excludeTrashed  Debe coincidir EXACTAMENTE con el índice real:
     *   - false → índice plano UNIQUE(business_id, columna): un registro con
     *     borrado lógico sigue ocupando el valor y la validación debe reflejarlo.
     *   - true  → candado parcial sobre columna generada (name_lock, email_lock):
     *     el borrado lógico libera el valor.
     *   Una discrepancia entre esta bandera y el motor convierte un 422 legible
     *   en un SQLSTATE 23000 no capturado, es decir, un 500.
     */
    protected function tenantUnique(
        string $table,
        string $column,
        bool $excludeTrashed = false
    ): Unique {
        $rule = Rule::unique($table, $column)->where('business_id', $this->businessId());

        return $excludeTrashed ? $rule->whereNull('deleted_at') : $rule;
    }

    /**
     * Normaliza el parámetro de ruta a entero, venga como escalar
     * o como modelo enlazado (route model binding).
     */
    protected function routeId(string $parameter): ?int
    {
        $value = $this->route($parameter);

        if ($value instanceof Model) {
            return (int) $value->getKey();
        }

        return $value === null ? null : (int) $value;
    }

    /**
     * Verdadero si el destinatario de la operación es el propio autor.
     * Base de los controles antiescalada y antiautobloqueo.
     */
    protected function targetsSelf(string $parameter = 'user'): bool
    {
        $target = $this->routeId($parameter);

        return $target !== null && $target === (int) $this->user()?->id;
    }
}
