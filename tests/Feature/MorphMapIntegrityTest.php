<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Tests\TestCase;

// Evita errores en producción y datos ocultos en las auditorías asegurando que 
// cada nuevo modelo esté registrado en config/gintly.php.
final class MorphMapIntegrityTest extends TestCase
{
    public function test_todo_modelo_del_dominio_figura_en_el_mapa(): void
    {
        $registrados = array_values(Relation::morphMap());
        $ausentes = [];

        foreach (glob(app_path('Models/*.php')) ?: [] as $archivo) {
            $clase = 'App\\Models\\'.Str::before(basename($archivo), '.php');

            if (! class_exists($clase) || ! is_subclass_of($clase, Model::class)) {
                continue;
            }

            if (! in_array($clase, $registrados, true)) {
                $ausentes[] = $clase;
            }
        }

        $this->assertSame([], $ausentes, sprintf(
            'Modelos sin alias en config/gintly.php (audit.morph_map): %s',
            implode(', ', $ausentes)
        ));
    }

    public function test_el_filtro_de_auditoria_coincide_con_el_mapa(): void
    {
        $this->assertSame(
            array_keys((array) config('gintly.audit.morph_map')),
            (array) config('gintly.audit.auditable_types'),
            'auditable_types debe derivarse de morph_map mediante array_keys().'
        );
    }

    public function test_los_alias_caben_en_las_columnas_del_esquema(): void
    {
        foreach (array_keys((array) config('gintly.audit.morph_map')) as $alias) {
            // anomalies.source_type es string(60): es la cota más estrecha.
            $this->assertLessThanOrEqual(60, strlen((string) $alias), "Alias demasiado largo: {$alias}");
        }
    }
}
