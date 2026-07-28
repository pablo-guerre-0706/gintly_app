<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountReceivableStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AccountReceivable extends Model
{
    use BelongsToBusiness;

    // El plural de la clase (AccountReceivable) no coincide con la tabla: se declara explícito.
    protected $table = 'accounts_receivables';

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'total_amount',
        'paid_amount',
        'due_date',
    ];
    // FUERA de fillable: business_id (trait), status (Service/cron), balance (motor).

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',              // Devuelve string ⇒ apto para bcmath.
            'paid_amount'  => 'decimal:2',
            'balance'      => 'decimal:2',
            'status'       => AccountReceivableStatus::class,
            'due_date'     => 'date',
        ];
    }

    // ---------------- Relaciones ----------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class, 'accounts_receivable_id');
    }

    // ---------------- Predicados de dominio ----------------

    public function isSettled(): bool
    {
        return $this->status->isSettled();
    }

    public function hasBalance(): bool
    {
        return bccomp((string) $this->balance, '0.00', 2) > 0;
    }

    // ---------------- Scopes ----------------

    /**
     * Cuentas con saldo vivo que pesan en la exposición del cliente
     * (pendiente/parcial/vencida). Sirve a la exposición (RF-08-02/06) y al guarda ERR-05B (P6).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', AccountReceivableStatus::exposureStatuses());
    }

    /** Candidatas del cron a marcarse 'vencida' (RF-08-05). */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [
                AccountReceivableStatus::Pendiente->value,
                AccountReceivableStatus::Parcial->value,
            ])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('balance', '>', 0);
    }
}
