<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockTransferStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


final class Warehouse extends Model
{
    use BelongsToBusiness;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    // True si tiene saldos con existencia o reserva: bloquea el borrado físico.
    public function hasStock(): bool
    {
        return $this->stockLevels()
            ->where(fn (Builder $q) => $q->where('quantity', '>', 0)->orWhere('reserved_quantity', '>', 0))
            ->exists();
    }

    // True si participa (origen o destino) en algún traspaso aún pendiente.
    public function hasPendingTransfers(): bool
    {
        return StockTransfer::query()
            ->where('status', StockTransferStatus::Pendiente->value)
            ->where(fn (Builder $q) => $q
                ->where('from_warehouse_id', $this->getKey())
                ->orWhere('to_warehouse_id', $this->getKey()))
            ->exists();
    }

    // True si es la predeterminada y no hay OTRA predeterminada activa en su sucursal:
    // darla de baja dejaría a la sucursal sin bodega por defecto para el POS.
    public function isUndesignatedDefault(): bool
    {
        if (! $this->is_default) {
            return false;
        }

        $anotherDefault = self::query()
            ->where('branch_id', $this->branch_id)
            ->where('is_default', true)
            ->whereKeyNot($this->getKey())
            ->exists();

        return ! $anotherDefault;
    }

    /**
     * Motivo por el que la bodega NO admite baja lógica, o null si es dable de baja.
     * El soft-delete es un UPDATE: las FK RESTRICT no actúan, así que la guarda es de dominio.
     */
    public function deletionBlocker(): ?string
    {
        if ($this->hasStock()) {
            return 'tiene existencias o reservas';
        }

        if ($this->hasPendingTransfers()) {
            return 'participa en traspasos pendientes';
        }

        if ($this->isUndesignatedDefault()) {
            return 'es la bodega predeterminada y no se ha designado otra';
        }

        return null;
    }
}

