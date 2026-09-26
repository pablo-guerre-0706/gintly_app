<?php

declare(strict_types=1);

namespace Tests\Feature\Provisioning;

use App\Models\AnomalyRule;
use App\Models\Business;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Observers\BusinessObserver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\MysqlTestCase;

/**
 * Remediacion transversal del BusinessObserver (RF-01-06).
 *
 * Antes, crear un negocio disparaba el observer, cuyo firstOrCreate descartaba
 * business_id/is_generic (no fillable) y fallaba con "business_id doesn't have a
 * default value". Ahora el aprovisionamiento se siembra con forceFill (explicito,
 * sin volver esas columnas asignables globalmente).
 *
 * Se verifica la creacion NORMAL de un negocio (con eventos) y sus datos iniciales
 * en MySQL, dentro de la transaccion de la prueba (revertida al terminar).
 */
final class BusinessProvisioningTest extends MysqlTestCase
{
    public function test_crear_negocio_siembra_datos_iniciales(): void
    {
        // Permisos globales + ROL-SYS (necesarios para syncBusinessRoles del observer).
        app(RolesAndPermissionsSeeder::class)->run();

        // Creacion NORMAL: el BusinessObserver::created se dispara (con eventos).
        $business = Business::create([
            'name'     => 'Negocio Provisioning',
            'slug'     => 'prov-'.uniqid(),
            'plan'     => 'basic',
            'status'   => 'active',
            'tax_rate' => '0.1500',
            'timezone' => 'America/Managua',
        ]);

        $this->assertNotNull($business->id);

        // Primera verificacion tras la creacion (observer disparado una vez).
        $this->assertProvisioning($business->id);

        // Idempotencia race-safe: re-ejecutar el aprovisionamiento sobre el MISMO
        // negocio no duplica nada (createOrFirst + UNIQUE del motor).
        app(BusinessObserver::class)->created($business);
        app(BusinessObserver::class)->created($business);

        $this->assertProvisioning($business->id);
    }

    /** Confirma los conteos EXACTOS del aprovisionamiento para un negocio. */
    private function assertProvisioning(int $businessId): void
    {
        // (a) EXACTAMENTE 1 cliente generico "Consumidor Final".
        $this->assertSame(
            1,
            Customer::withoutGlobalScopes()->where('business_id', $businessId)->where('is_generic', true)->count(),
            'Debe haber exactamente 1 cliente generico.'
        );
        $this->assertSame(
            'Consumidor Final',
            Customer::withoutGlobalScopes()->where('business_id', $businessId)->where('is_generic', true)->value('name')
        );

        // (b) EXACTAMENTE las 4 secuencias esperadas.
        $sequences = DocumentSequence::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->pluck('document_type')
            ->map(fn ($v) => $v instanceof \BackedEnum ? $v->value : (string) $v)
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['credit_note', 'invoice', 'sale', 'sales_return'], $sequences);

        // (c) EXACTAMENTE 6 reglas de anomalia.
        $this->assertSame(
            6,
            AnomalyRule::withoutGlobalScopes()->where('business_id', $businessId)->count()
        );

        // (d) EXACTAMENTE los roles ROL-01/02/03 del negocio (team = business_id).
        $roles = DB::table('roles')->where('business_id', $businessId)->pluck('name')->sort()->values()->all();
        $this->assertSame(['ROL-01', 'ROL-02', 'ROL-03'], $roles);
    }
}
