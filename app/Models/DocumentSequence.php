<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use HasFactory, BelongsToBusiness;

    protected $fillable = ['document_type', 'prefix', 'next_number'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }
    // business() del trait. document_type queda como string (constreñido por el ENUM de motor).
}
