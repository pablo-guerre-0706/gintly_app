<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PeriodType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KpiSnapshot extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null; // Caché: solo created_at.

    // Escrito exclusivamente por KpiService (recálculo idempotente).
    protected $fillable = [
        'business_id',
        'branch_id',
        'kpi_code',
        'period_type',
        'period_start',
        'period_end',
        'value',
        'target_value',
        'achievement_pct',
        'metadata',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_type'     => PeriodType::class,
            'period_start'    => 'date',
            'period_end'      => 'date',
            'value'           => 'decimal:4',
            'target_value'    => 'decimal:2',
            'achievement_pct' => 'decimal:2',
            'metadata'        => 'array',
            'calculated_at'   => 'immutable_datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Etiqueta legible resuelta del registro canónico (RF-12-02). */
    public function label(): string
    {
        return (string) (config("kpis.{$this->kpi_code}.label") ?? $this->kpi_code);
    }

    public function family(): string
    {
        return (string) (config("kpis.{$this->kpi_code}.family") ?? 'general');
    }

    public function unit(): string
    {
        return (string) (config("kpis.{$this->kpi_code}.unit") ?? 'valor');
    }
}
