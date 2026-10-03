<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnomalyRuleCode;
use App\Enums\AnomalySeverity;
use App\Enums\AnomalyThresholdType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AnomalyRule extends Model
{
    use BelongsToBusiness;

    // code y name son inmutables (catálogo cerrado): solo se parametriza el resto.
    protected $fillable = [
        'threshold_value',
        'default_severity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'code'             => AnomalyRuleCode::class,
            'threshold_type'   => AnomalyThresholdType::class,
            'default_severity' => AnomalySeverity::class,
            'threshold_value'  => 'decimal:2',
            'is_active'        => 'boolean',
        ];
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomaly::class);
    }
}
