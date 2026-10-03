<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentSequenceType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contador de folios secuenciales por negocio y tipo. Reutilizada para NC en MOD-10.
 */
final class DocumentSequence extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    protected $fillable = [
        'document_type',
        'prefix',
        'next_number',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentSequenceType::class,
            'next_number'   => 'integer',
        ];
    }
}

