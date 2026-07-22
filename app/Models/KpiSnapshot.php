<?php

namespace App\Models;

use App\Enums\KpiPeriodType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiSnapshot extends Model
{
    use HasFactory, BelongsToBusiness;

    const UPDATED_AT = null;   // el .md: solo created_at

    protected $fillable = [
        'branch_id', 'kpi_code', 'period_type', 'period_start', 'period_end',
        'value', 'target_value', 'achievement_pct', 'metadata', 'calculated_at',
        // 'branch_key' EXCLUIDO: columna generada.
    ];

    protected function casts(): array
    {
        return [
            'period_type'     => KpiPeriodType::class,
            'period_start'    => 'date',
            'period_end'      => 'date',
            'value'           => 'decimal:4',
            'target_value'    => 'decimal:2',
            'achievement_pct' => 'decimal:2',
            'metadata'        => 'array',
            'calculated_at'   => 'datetime',
            'created_at'      => 'immutable_datetime',
        ];
        // kpi_code queda string: caché flexible, registro canónico en config/kpis.php.
    }

    // business() del trait
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
}
