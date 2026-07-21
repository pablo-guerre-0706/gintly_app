<?php

namespace App\Models;

use App\Enums\AnomalyRuleCode;
use App\Enums\AnomalySeverity;
use App\Enums\ThresholdType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnomalyRule extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'code', 'name', 'threshold_value', 'threshold_type', 'default_severity', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'code'             => AnomalyRuleCode::class,
            'threshold_value'  => 'decimal:2',
            'threshold_type'   => ThresholdType::class,
            'default_severity' => AnomalySeverity::class,
            'is_active'        => 'boolean',
        ];
    }

    // business() del trait
    public function anomalies(): HasMany { return $this->hasMany(Anomaly::class); }
}
