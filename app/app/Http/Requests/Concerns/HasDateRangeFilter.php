<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Carbon\CarbonImmutable;

/**
 * Filtro por rango de fechas, interpretado en el huso horario del negocio.
 *
 * `businesses.timezone` rige los cortes de periodo. Un rango evaluado en UTC
 * cuando el negocio opera en America/Managua desplaza el corte seis horas:
 * las operaciones de las últimas horas del día caen en el día siguiente y
 * los totales no cuadran contra los reportes.
 *
 * Los helpers devuelven límites en UTC, listos para el `whereBetween`.
 */
trait HasDateRangeFilter
{
    protected string $dateFilterFormat = 'Y-m-d';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function dateRangeRules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:'.$this->dateFilterFormat],
            'to'   => ['sometimes', 'date_format:'.$this->dateFilterFormat, 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function dateRangeMessages(): array
    {
        return [
            'from.date_format'    => 'La fecha inicial debe expresarse en formato AAAA-MM-DD.',
            'to.date_format'      => 'La fecha final debe expresarse en formato AAAA-MM-DD.',
            'to.after_or_equal'   => 'La fecha final no puede ser anterior a la fecha inicial.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function dateRangeAttributes(): array
    {
        return [
            'from' => 'fecha inicial',
            'to'   => 'fecha final',
        ];
    }

    public function fromDateTime(): ?CarbonImmutable
    {
        return $this->boundary('from', startOfDay: true);
    }

    public function toDateTime(): ?CarbonImmutable
    {
        return $this->boundary('to', startOfDay: false);
    }

    private function boundary(string $key, bool $startOfDay): ?CarbonImmutable
    {
        if (! $this->filled($key)) {
            return null;
        }

        // El prefijo '!' pone a cero los componentes no especificados;
        // sin él, Carbon hereda la hora actual y el rango se vuelve móvil.
        $date = CarbonImmutable::createFromFormat(
            '!'.$this->dateFilterFormat,
            (string) $this->input($key),
            $this->businessTimezone()
        );

        // El límite superior es inclusivo: `to=2026-07-22` debe abarcar
        // todo ese día, no cortar a las 00:00:00.
        return ($startOfDay ? $date->startOfDay() : $date->endOfDay())->utc();
    }
}
