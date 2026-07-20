<?php

namespace App\Models;

use App\Enums\CashSessionStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'cash_register_id',
        'opened_by',
        'opening_amount',
        'opened_at',
        'closing_notes',
        // status → default 'abierta'. Los campos de cierre (expected/counted/denominations/
        // closed_*/status) los asigna el CashService por Asignacion directa, nunca por API.
        // 'difference' es columna GENERADA: jamás se asigna.
    ];

    protected function casts(): array
    {
        return [
            'status'                => CashSessionStatus::class,
            'opening_amount'        => 'decimal:2',
            'expected_amount'       => 'decimal:2',
            'counted_amount'        => 'decimal:2',
            'difference'            => 'decimal:2',   // read-only (motor)
            'counted_denominations' => 'array',       // JSON (evidencia del arqueo)
            'opened_at'             => 'datetime',
            'closed_at'             => 'datetime',
        ];
    }

    // business() del trait

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
