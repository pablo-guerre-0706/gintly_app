<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Contrato único de paginación y ordenamiento (H-04).
 *
 * El tope de `per_page` no es cosmético: sin él, `?per_page=1000000`
 * es una denegación de servicio de un solo carácter.
 *
 * El allowlist de `sort` tampoco: la columna de ordenamiento se interpola
 * en un `orderBy`, y sin restricción es un vector de inyección SQL.
 */
trait HasPaginationRules
{
    protected int $defaultPerPage = 25;

    protected int $maxPerPage = 100;

    /**
     * Columnas ordenables. Cada Index Request la define explícitamente.
     *
     * @return array<int, string>
     */
    abstract protected function sortableColumns(): array;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function paginationRules(): array
    {
        return [
            'page'      => ['sometimes', 'integer', 'min:1'],
            'per_page'  => ['sometimes', 'integer', 'min:1', 'max:'.$this->maxPerPage],
            'sort'      => ['sometimes', 'string', Rule::in($this->sortableColumns())],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function paginationMessages(): array
    {
        return [
            'page.integer'      => 'El número de página debe ser un valor entero.',
            'page.min'          => 'El número de página debe ser igual o mayor que 1.',
            'per_page.integer'  => 'La cantidad de registros por página debe ser un valor entero.',
            'per_page.min'      => 'La cantidad de registros por página debe ser igual o mayor que 1.',
            'per_page.max'      => 'La cantidad de registros por página no puede exceder los '.$this->maxPerPage.'.',
            'sort.in'           => 'El campo de ordenamiento solicitado no está permitido.',
            'direction.in'      => 'El sentido de ordenamiento debe ser ascendente (asc) o descendente (desc).',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function paginationAttributes(): array
    {
        return [
            'page'      => 'página',
            'per_page'  => 'registros por página',
            'sort'      => 'campo de ordenamiento',
            'direction' => 'sentido de ordenamiento',
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', $this->defaultPerPage);
    }

    public function sortColumn(string $fallback = 'created_at'): string
    {
        $sort = (string) $this->input('sort', $fallback);

        return in_array($sort, $this->sortableColumns(), true) ? $sort : $fallback;
    }

    public function sortDirection(string $fallback = 'desc'): string
    {
        $direction = strtolower((string) $this->input('direction', $fallback));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : $fallback;
    }

    /**
     * Los formularios envían `?search=&is_active=` cuando el usuario no toca
     * el filtro. Una cadena vacía no es "ausente" para el validador: rompe
     * `boolean` y `integer`. Se descartan antes de validar.
     */
    protected function stripEmptyFilters(): void
    {
        $clean = array_filter(
            $this->all(),
            static fn ($value) => ! (is_string($value) && trim($value) === '')
        );

        $this->replace($clean);
    }
}
