<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\Immutable;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


final class CashMovement extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'cash_session_id',
        'user_id',
        'type',
        'category',
        'payment_method',
        'amount',
        'sale_id',
        'authorized_by',
        'description',
    ];

    /**
    * Red de contención de consistencia financiera en el ORM.
    * El perímetro HTTP ya valida type⇄category vía FormRequest (422). Esto blinda
    * las vías no-HTTP (Jobs, consola, otros Services): si una categoría de dirección
    * forzada se contradice con el type asignado, se aborta la persistencia de inmediato.
    */
    protected static function booted(): void
    {
        static::creating(function (CashMovement $movement): void {
            $forcedType = $movement->category->forcedType();

            if ($forcedType !== null && $forcedType !== $movement->type) {
                throw new InvalidArgumentException(sprintf(
                    'Incoherencia en cash_movements: la categoría «%s» exige el tipo «%s», pero se recibió «%s». Persistencia abortada.',
                    $movement->category->value,
                    $forcedType->value,
                    $movement->type->value,
                ));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type'           => CashMovementType::class,
            'category'       => CashMovementCategory::class,
            'payment_method' => PaymentMethod::class,
            'amount'         => 'decimal:2',
            'created_at'     => 'immutable_datetime',
        ];
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function scopeCash(Builder $query): Builder
    {
        return $query->where('payment_method', PaymentMethod::Efectivo->value);
    }
}
