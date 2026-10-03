<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditNoteResolutionType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una vía de aplicación del resarcimiento de una nota de crédito (MOD-10). Append-only:
 * el desglose de cómo se resarció una devolución no se edita ni se borra.
 */
final class CreditNoteResolution extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null; // Solo created_at.

    protected $fillable = [
        'credit_note_id',
        'cash_session_id',
        'resolution_type',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'resolution_type' => CreditNoteResolutionType::class,
            'amount'          => 'decimal:2',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }
}
