<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = [
        'customer_id',   // validar mismo tenant en FormRequest
        'label',
        'address_line',
        'reference',
        'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    // business() del trait

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
