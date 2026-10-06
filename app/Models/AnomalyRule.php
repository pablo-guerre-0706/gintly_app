<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class AnomalyRule extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'business_id',
        'code',             // <--- VITAL: Debe estar aquí para que el firstOrCreate lo evalúe
        'name',
        'threshold_type',
        'default_severity',
        'threshold_value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold_value' => 'decimal:2',
            'is_active'       => 'boolean',
        ];
    }
}