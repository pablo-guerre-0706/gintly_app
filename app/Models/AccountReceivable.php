<?php

namespace App\Models;

use App\Enums\AccountReceivableStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class AccountReceivable extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'total_amount',
        'paid_amount',
        'status',
        'due_date',
        // 'balance' EXCLUIDO: columna generada (motor). Jamás se asigna.
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_amount'  => 'decimal:2',
            'balance'      => 'decimal:2',   // read-only
            'status'       => AccountReceivableStatus::class,
            'due_date'     => 'date',
        ];
    }

    protected static function booted(): void
    {
        // RF-08-05: 'vencida' - estado derivado del tiempo (cron), nunca asignado a mano al crear.
        static::creating(function (AccountReceivable $ar): void {
            if ($ar->status === AccountReceivableStatus::Vencida) {
                throw new InvalidArgumentException('Una CxC no puede nacer vencida: es un estado derivado por el cron.');
            }
        });
    }

    // business() del trait

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
}
