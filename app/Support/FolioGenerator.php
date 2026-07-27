<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentSequenceType;
use App\Models\DocumentSequence;

/**
 * Genera el folio fiscal secuencial bloqueando SOLO la fila del contador 
 * (document_sequences), no la tabla de facturas.
 *
 * Reutilizable para notas de crédito (MOD-10) cambiando el DocumentSequenceType.
 */
final class FolioGenerator
{
    public function next(int $businessId, DocumentSequenceType $type): string
    {
        $sequence = DocumentSequence::query()
            ->where('business_id', $businessId)
            ->where('document_type', $type->value)
            ->lockForUpdate()
            ->first();

        // El observer siembra la secuencia al crear el negocio (P5). Si no
        // existiera (negocio previo a P5), se crea al vuelo con el prefijo canónico.
        if ($sequence === null) {
            $sequence = new DocumentSequence([
                'document_type' => $type,
                'prefix'        => $type->defaultPrefix(),
                'next_number'   => 1,
            ]);
            $sequence->business_id = $businessId;
            $sequence->save();

            $sequence = DocumentSequence::query()
                ->whereKey($sequence->getKey())
                ->lockForUpdate()
                ->firstOrFail();
        }

        $number = (int) $sequence->next_number;
        $sequence->next_number = $number + 1;
        $sequence->save();

        // Folio = prefijo + correlativo con relleno (F-000001).
        return $sequence->prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
