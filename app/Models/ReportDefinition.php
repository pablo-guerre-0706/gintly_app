<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportDefinition extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'user_id', 'name', 'report_type', 'filters', 'is_scheduled', 'schedule_cron',
    ];

    protected function casts(): array
    {
        return [
            'filters'      => 'array',
            'is_scheduled' => 'boolean',   // motor de envíos → Fase 2
        ];
    }

    // business() del trait
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}