<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;


// Generador de folios secuenciales atómicos por negocio y tipo de documento.
final class SequenceGenerator
{
    public function next(int $businessId, string $type, string $prefix, int $padding = 6): string
    {
        return DB::transaction(function () use ($businessId, $type, $prefix, $padding): string {
            $row = DB::table('sequences')
                ->where('business_id', $businessId)
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('sequences')->insert([
                    'business_id' => $businessId,
                    'type'        => $type,
                    'next_value'  => 2,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                $current = 1;
            } else {
                $current = (int) $row->next_value;
                DB::table('sequences')
                    ->where('business_id', $businessId)
                    ->where('type', $type)
                    ->update(['next_value' => $current + 1, 'updated_at' => now()]);
            }

            return $prefix.str_pad((string) $current, $padding, '0', STR_PAD_LEFT);
        });
    }
}
