<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GeocodeSource;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MOD-04 · Ubicación (dirección georreferenciable) de un proveedor. Una ubicación entra al mapa solo cuando
 * está CONFIRMADA (confirmed_at NOT NULL). Al cambiar la dirección se invalida su confirmación anterior
 * (lo hace SupplierLocationService). `is_primary` único por proveedor (candado de motor).
 */
final class SupplierLocation extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'supplier_id',
        'address',
        'latitude',
        'longitude',
        'geocode_source',
        'external_id',
        'quality',
        'geocoded_at',
        'confirmed_at',
        'confirmed_by',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'latitude'       => 'decimal:7',
            'longitude'      => 'decimal:7',
            'geocode_source' => GeocodeSource::class,
            'geocoded_at'    => 'datetime',
            'confirmed_at'   => 'datetime',
            'is_primary'     => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
