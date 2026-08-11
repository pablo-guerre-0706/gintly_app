<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReportDefinition extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'name',
        'report_type',
        'filters',
        'is_scheduled',
        'schedule_cron',
    ];
    // FUERA de fillable: user_id (lo fija el Service/controlador desde la sesión).

    protected function casts(): array
    {
        return [
            'filters'      => 'array',
            'is_scheduled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
