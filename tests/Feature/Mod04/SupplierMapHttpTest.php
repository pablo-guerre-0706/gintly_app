<?php

declare(strict_types=1);

namespace Tests\Feature\Mod04;

use App\Contracts\Geocoder;
use App\Enums\RoleName;
use App\Enums\SupplierStatus;
use App\Models\Business;
use App\Models\Supplier;
use App\Models\SupplierLocation;
use App\Models\User;
use App\Support\Geocoding\ArrayGeocoder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-04 · Proveedores candidatos + ubicaciones del mapa + geocodificación desacoplada. Candidato sin
 * aprobación; fallo/ausencia de geocoder NO revierte la aprobación; confirmación manual; cambio de dirección
 * invalida la confirmación; suspensión saca del mapa; aislamiento entre negocios.
 */
final class SupplierMapHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    private function asUser(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $r = app(PermissionRegistrar::class);
        $r->setPermissionsTeamId(null);
        $r->forgetCachedPermissions();
        $auth = User::query()->whereKey($u->getKey())->firstOrFail();
        if (! $auth instanceof AuthenticatableContract) {
            throw new \RuntimeException('no auth');
        }

        return $this->actingAs($auth, 'web');
    }

    private function seedTenant(string $slug): object
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(RolesAndPermissionsSeeder::class)->run();

        $business = Business::create([
            'name' => 'Neg '.$slug, 'slug' => $slug.'-'.(++self::$seq),
            'plan' => 'basic', 'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
        ]);
        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);

        return (object) compact('business', 'owner', 'admin');
    }

    private function makeUser(Business $b, RoleName $role): User
    {
        $u = new User(['name' => $role->value.self::$seq, 'email' => 'u'.(++self::$seq).'@t.local', 'password' => Hash::make('secret-Password-123'), 'is_active' => true]);
        $u->business_id = $b->id;
        $u->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
        $u->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $u;
    }

    private function fakeGeocoder(array $results): void
    {
        $this->app->instance(Geocoder::class, new ArrayGeocoder($results));
    }

    /** Crea un supplier (opcionalmente con ubicación) como el actor dado; devuelve el id. */
    private function createSupplier(object $t, User $actor, array $payload): int
    {
        return $this->asUser($actor)->postJson('/api/v1/suppliers', $payload)->assertCreated()->json('data.id');
    }

    // ---------------- 1) Candidato sin aprobación ----------------

    public function test_rol01_registra_candidato_externo_sin_aprobar(): void
    {
        $t = $this->seedTenant('a');

        $resp = $this->asUser($t->owner)->postJson('/api/v1/suppliers', [
            'name' => 'Candidato Mapa',
            'location' => ['address' => 'Mercado Oriental, Managua', 'latitude' => '12.1500000', 'longitude' => '-86.2500000', 'external_id' => 'osm-123'],
        ])->assertCreated();

        // Nace PENDIENTE (nunca aprobado automáticamente aunque venga del mapa externo).
        $resp->assertJsonPath('data.status', 'pendiente')
            ->assertJsonPath('data.locations.0.geocode_source', 'external')
            ->assertJsonPath('data.locations.0.confirmed', false);

        $supplierId = $resp->json('data.id');
        // No aparece en el mapa (ni aprobado ni confirmado).
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_rol02_conserva_creacion_de_proveedor(): void
    {
        $t = $this->seedTenant('a');
        $this->asUser($t->admin)->postJson('/api/v1/suppliers', ['name' => 'Prov Admin'])
            ->assertCreated()->assertJsonPath('data.status', 'pendiente');
    }

    // ---------------- 2) Geocodificación: fallo/ausencia no bloquea aprobación ----------------

    public function test_aprobacion_con_geocoder_nulo_deja_ubicacion_pendiente_sin_revertir(): void
    {
        $t = $this->seedTenant('a');
        // Driver por defecto 'null' (sin proveedor configurado): la geocodificación no resuelve nada.
        $id = $this->createSupplier($t, $t->owner, ['name' => 'Prov Sin Geo', 'location' => ['address' => 'Direccion sin geocoder']]);

        $this->asUser($t->owner)->postJson("/api/v1/suppliers/{$id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'aprobado'); // aprobado pese a no geocodificar.

        $loc = SupplierLocation::withoutGlobalScopes()->where('supplier_id', $id)->firstOrFail();
        $this->assertNull($loc->latitude);   // ubicación pendiente.
        $this->assertFalse($loc->isConfirmed());
        // Sigue aprobado y fuera del mapa (sin ubicación confirmada).
        $this->assertSame('aprobado', Supplier::withoutGlobalScopes()->findOrFail($id)->status->value);
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_aprobacion_geocodifica_cuando_hay_proveedor_pero_no_confirma(): void
    {
        $t = $this->seedTenant('a');
        $this->fakeGeocoder(['Plaza Inter, Managua' => ['latitude' => '12.1400000', 'longitude' => '-86.2700000', 'quality' => 'rooftop']]);
        $id = $this->createSupplier($t, $t->owner, ['name' => 'Prov Geo', 'location' => ['address' => 'Plaza Inter, Managua']]);

        $this->asUser($t->owner)->postJson("/api/v1/suppliers/{$id}/approve")->assertOk();

        $loc = SupplierLocation::withoutGlobalScopes()->where('supplier_id', $id)->firstOrFail();
        $this->assertSame(0, bccomp((string) $loc->latitude, '12.14', 5)); // geocodificó.
        $this->assertSame('geocoded', $loc->geocode_source->value);
        $this->assertFalse($loc->isConfirmed()); // geocodificar NO confirma.
        // Aún no está en el mapa (falta confirmación).
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertOk()->assertJsonCount(0, 'data');
    }

    // ---------------- 3) Confirmación manual y aparición en el mapa ----------------

    public function test_confirmacion_manual_pone_al_proveedor_en_el_mapa(): void
    {
        $t = $this->seedTenant('a');
        $id = $this->createSupplier($t, $t->owner, ['name' => 'Prov Manual', 'location' => ['address' => 'Rotonda Metrocentro']]);
        $this->asUser($t->owner)->postJson("/api/v1/suppliers/{$id}/approve")->assertOk();
        $loc = SupplierLocation::withoutGlobalScopes()->where('supplier_id', $id)->firstOrFail();

        // Confirmar/corregir manualmente el marcador con coordenadas.
        $this->asUser($t->admin)->postJson("/api/v1/suppliers/{$id}/locations/{$loc->id}/confirm", [
            'latitude' => '12.1340000', 'longitude' => '-86.2680000',
        ])->assertOk()->assertJsonPath('data.confirmed', true)->assertJsonPath('data.geocode_source', 'manual');

        // Ahora SÍ aparece en el mapa (aprobado + activo + ubicación confirmada).
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.locations.0.latitude', fn ($v) => $v !== null);
    }

    public function test_confirmar_sin_coordenadas_disponibles_rechaza_422(): void
    {
        $t = $this->seedTenant('a');
        $id = $this->createSupplier($t, $t->owner, ['name' => 'Prov NoCoord', 'location' => ['address' => 'Sin coords']]);
        $loc = SupplierLocation::withoutGlobalScopes()->where('supplier_id', $id)->firstOrFail();

        // Confirmar sin lat/lng y sin geocodificación previa → 422.
        $this->asUser($t->admin)->postJson("/api/v1/suppliers/{$id}/locations/{$loc->id}/confirm", [])
            ->assertStatus(422)->assertJsonValidationErrors(['latitude']);
    }

    public function test_coordenadas_fuera_de_rango_rechazadas_422(): void
    {
        $t = $this->seedTenant('a');
        $id = $this->createSupplier($t, $t->owner, ['name' => 'Prov Rango']);

        $this->asUser($t->admin)->postJson("/api/v1/suppliers/{$id}/locations", [
            'address' => 'X', 'latitude' => '99.0', 'longitude' => '0.0',
        ])->assertStatus(422)->assertJsonValidationErrors(['latitude']);
    }

    // ---------------- 4) Cambio de dirección invalida la confirmación ----------------

    public function test_cambio_de_direccion_invalida_confirmacion_y_sale_del_mapa(): void
    {
        $t = $this->seedTenant('a');
        $id = $this->createSupplier($t, $t->owner, ['name' => 'Prov Cambia', 'location' => ['address' => 'Dir Vieja']]);
        $this->asUser($t->owner)->postJson("/api/v1/suppliers/{$id}/approve")->assertOk();
        $loc = SupplierLocation::withoutGlobalScopes()->where('supplier_id', $id)->firstOrFail();
        $this->asUser($t->admin)->postJson("/api/v1/suppliers/{$id}/locations/{$loc->id}/confirm", ['latitude' => '12.1', 'longitude' => '-86.2'])->assertOk();
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertOk()->assertJsonCount(1, 'data'); // en el mapa

        // Cambiar la dirección invalida la confirmación y las coordenadas.
        $this->asUser($t->admin)->putJson("/api/v1/suppliers/{$id}/locations/{$loc->id}", ['address' => 'Dir Nueva'])
            ->assertOk()->assertJsonPath('data.confirmed', false)->assertJsonPath('data.latitude', null);

        // Sale del mapa (ya no hay ubicación confirmada).
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertOk()->assertJsonCount(0, 'data');
    }

    // ---------------- 5) Suspensión saca del mapa ----------------

    public function test_suspension_saca_al_proveedor_del_mapa(): void
    {
        $t = $this->seedTenant('a');
        $id = $this->createSupplier($t, $t->owner, ['name' => 'Prov Susp', 'location' => ['address' => 'Dir']]);
        $this->asUser($t->owner)->postJson("/api/v1/suppliers/{$id}/approve")->assertOk();
        $loc = SupplierLocation::withoutGlobalScopes()->where('supplier_id', $id)->firstOrFail();
        $this->asUser($t->admin)->postJson("/api/v1/suppliers/{$id}/locations/{$loc->id}/confirm", ['latitude' => '12.1', 'longitude' => '-86.2'])->assertOk();
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertOk()->assertJsonCount(1, 'data');

        // Suspender (ROL-01) → desaparece del mapa (status != aprobado).
        $this->asUser($t->owner)->postJson("/api/v1/suppliers/{$id}/suspend")->assertOk()->assertJsonPath('data.status', 'suspendido');
        $this->asUser($t->owner)->getJson('/api/v1/map/suppliers')->assertOk()->assertJsonCount(0, 'data');
    }

    // ---------------- 6) Geocode endpoint directo ----------------

    public function test_endpoint_geocode_resuelve_y_no_confirma(): void
    {
        $t = $this->seedTenant('a');
        $this->fakeGeocoder(['Km 5 Carretera Norte' => ['latitude' => '12.1200000', 'longitude' => '-86.2200000']]);
        $id = $this->createSupplier($t, $t->owner, ['name' => 'Prov GeoEP', 'location' => ['address' => 'Km 5 Carretera Norte']]);
        $loc = SupplierLocation::withoutGlobalScopes()->where('supplier_id', $id)->firstOrFail();

        $this->asUser($t->admin)->postJson("/api/v1/suppliers/{$id}/locations/{$loc->id}/geocode")
            ->assertOk()->assertJsonPath('data.geocode_source', 'geocoded')->assertJsonPath('data.confirmed', false);
    }

    // ---------------- 7) Aislamiento entre negocios ----------------

    public function test_aislamiento_entre_negocios_en_mapa_y_ubicaciones(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $id = $this->createSupplier($a, $a->owner, ['name' => 'Prov A', 'location' => ['address' => 'Dir A']]);
        $this->asUser($a->owner)->postJson("/api/v1/suppliers/{$id}/approve")->assertOk();
        $loc = SupplierLocation::withoutGlobalScopes()->where('supplier_id', $id)->firstOrFail();
        $this->asUser($a->admin)->postJson("/api/v1/suppliers/{$id}/locations/{$loc->id}/confirm", ['latitude' => '12.1', 'longitude' => '-86.2'])->assertOk();

        // El negocio B no ve el proveedor de A en su mapa.
        $this->asUser($b->owner)->getJson('/api/v1/map/suppliers')->assertOk()->assertJsonCount(0, 'data');
        // Ni puede listar/confirmar sus ubicaciones (BusinessScope → 404 en el binding del proveedor).
        $this->asUser($b->admin)->getJson("/api/v1/suppliers/{$id}/locations")->assertNotFound();
        $this->asUser($b->admin)->postJson("/api/v1/suppliers/{$id}/locations/{$loc->id}/confirm", ['latitude' => '1', 'longitude' => '1'])->assertNotFound();
    }

    public function test_rol03_no_administra_ubicaciones(): void
    {
        $t = $this->seedTenant('a');
        $id = $this->createSupplier($t, $t->owner, ['name' => 'Prov X', 'location' => ['address' => 'Dir']]);
        $operator = $this->makeUser($t->business, RoleName::Operator);

        // ROL-03 (sin perfil) no puede añadir ni confirmar ubicaciones.
        $this->asUser($operator)->postJson("/api/v1/suppliers/{$id}/locations", ['address' => 'Y'])->assertStatus(403);
    }
}
