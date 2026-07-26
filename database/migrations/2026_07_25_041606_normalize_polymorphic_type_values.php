<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


// Normaliza a alias los valores polimórficos ya persistidos como FQCN
return new class extends Migration
{
    /**
     * Columnas polimórficas del esquema, en orden de criticidad.
     *
     * @var array<int, array{table: string, column: string}>
     */
    private array $morphColumns = [
        ['table' => 'model_has_roles',       'column' => 'model_type'],
        ['table' => 'model_has_permissions', 'column' => 'model_type'],
        ['table' => 'audit_logs',            'column' => 'auditable_type'],
        ['table' => 'anomalies',             'column' => 'source_type'],
    ];

    public function up(): void
    {
        $this->rewrite(
            fn (array $map): array => $map
        );
    }

    // Revierte a nombres de clase completamente calificados.
    public function down(): void
    {
        $this->rewrite(
            fn (array $map): array => array_flip($map)
        );
    }

    /**
     * @param  \Closure(array<string, string>): array<string, string>  $direction
     */
    private function rewrite(Closure $direction): void
    {
        /** @var array<string, string> $morphMap alias => FQCN */
        $morphMap = (array) config('gintly.audit.morph_map', []);

        if ($morphMap === []) {
            throw new RuntimeException(
                'config/gintly.php no define audit.morph_map. '
                .'Publique el archivo de configuración antes de migrar.'
            );
        }

        // up():   FQCN => alias    ·    down():   alias => FQCN
        $replacements = $direction(array_flip($morphMap));

        foreach ($this->morphColumns as ['table' => $table, 'column' => $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach ($replacements as $from => $to) {
                DB::table($table)
                    ->where($column, $from)
                    ->update([$column => $to]);
            }
        }
    }
};
