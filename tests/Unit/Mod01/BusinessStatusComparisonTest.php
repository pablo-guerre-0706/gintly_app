<?php

declare(strict_types=1);

namespace Tests\Unit\Mod01;

use App\Enums\BusinessStatus;
use PHPUnit\Framework\TestCase;

/**
 * Raíz del defecto corregido en BusinessPolicy::update.
 *
 * `Business::$status` está casteado a BusinessStatus, por lo que la comparación
 * previa `$business->status === 'suspended'` era SIEMPRE falsa: un enum nunca es
 * idéntico a un string. La guarda de suspensión no se disparaba y un negocio
 * suspendido podía editar su configuración. La corrección usa `canOperate()`.
 *
 * No requiere base de datos.
 */
final class BusinessStatusComparisonTest extends TestCase
{
    public function test_el_enum_nunca_es_identico_al_string_suspended(): void
    {
        // Exactamente la comparación errónea que vivía en la política.
        $this->assertFalse(BusinessStatus::Suspended === 'suspended');
    }

    public function test_canoperate_distingue_suspendido_de_operativo(): void
    {
        $this->assertFalse(BusinessStatus::Suspended->canOperate());
        $this->assertTrue(BusinessStatus::Active->canOperate());
        $this->assertTrue(BusinessStatus::Trial->canOperate());
    }

    public function test_la_guarda_corregida_solo_bloquea_al_suspendido(): void
    {
        // Réplica de la condición nueva: `! $business->status->canOperate()`.
        $this->assertTrue(! BusinessStatus::Suspended->canOperate(), 'Suspendido: se bloquea.');
        $this->assertFalse(! BusinessStatus::Active->canOperate(), 'Activo: se permite.');
        $this->assertFalse(! BusinessStatus::Trial->canOperate(), 'Prueba: se permite.');
    }
}
