<?php

declare(strict_types=1);

namespace Tests\Feature\Mod03;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\TaxClass;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\UserOperativeProfile;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-03 (microcierre de aceptación frontend) · dos brechas:
 *  1. Unidad de medida legible: /stock y /physical-counts deben cargar product.unit (incluido el conteo recién
 *     registrado), reutilizando ProductResource/UnitResource, sin N+1 ni abrir /units.
 *  2. PUT /stock/{product}/{warehouse}/thresholds devolvía 500 (`Object of class Product could not be converted
 *     to int`) al castear el modelo enlazado a int; ahora resuelve la clave del binding y responde 200.
 */
final class StockUnitAndThresholdsHttpTest extends MysqlTestCase
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
        $branchA = $this->makeBranch($business, 'SA');
        $whA1 = $this->makeWarehouse($business, $branchA, 'A1', true);

        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);
        $bodA  = $this->makeUser($business, RoleName::Operator, $branchA, 'bodeguero');

        $category = new Category(['name' => 'Cat '.self::$seq]);
        $category->business_id = $business->id;
        $category->save();
        // Unidad con abreviatura legible ('kg') para verificar la serialización.
        $unit = new UnitOfMeasure(['name' => 'Kilogramo', 'abbreviation' => 'kg'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();

        return (object) compact('business', 'branchA', 'whA1', 'owner', 'admin', 'bodA', 'category', 'unit');
    }

    private function makeBranch(Business $b, string $name): Branch
    {
        $branch = new Branch;
        $branch->forceFill(['business_id' => $b->id, 'name' => $name.(++self::$seq), 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        return $branch;
    }

    private function makeWarehouse(Business $b, Branch $branch, string $name, bool $default): Warehouse
    {
        $w = new Warehouse;
        $w->forceFill(['business_id' => $b->id, 'branch_id' => $branch->id, 'name' => $name.(++self::$seq), 'is_default' => $default, 'is_active' => true])->save();

        return $w;
    }

    private function makeUser(Business $b, RoleName $role, ?Branch $branch = null, ?string $profile = null): User
    {
        $u = new User(['name' => $role->value.self::$seq, 'email' => 'u'.(++self::$seq).'@t.local', 'password' => Hash::make('secret-Password-123'), 'is_active' => true, 'branch_id' => $branch?->id]);
        $u->business_id = $b->id;
        $u->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
        $u->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        if ($profile !== null) {
            $row = new UserOperativeProfile(['profile' => $profile]);
            $row->user_id = $u->id;
            $row->business_id = $b->id;
            $row->save();
        }

        return $u;
    }

    private function makeProduct(object $t): Product
    {
        $p = new Product([
            'category_id' => $t->category->id, 'unit_id' => $t->unit->id, 'sku' => 'SKU-'.(++self::$seq),
            'name' => 'P '.self::$seq, 'type' => ProductType::Simple, 'sale_price' => '20.00', 'cost' => '10.00',
            'tracks_inventory' => true, 'tax_class' => TaxClass::Standard->value, 'is_active' => true,
        ]);
        $p->business_id = $t->business->id;
        $p->save();

        return $p;
    }

    private function seedStock(object $t, Warehouse $w, Product $p, string $qty, string $reserved = '0.000', ?string $min = null, ?string $max = null): StockLevel
    {
        $s = new StockLevel;
        $s->forceFill([
            'business_id' => $t->business->id, 'product_id' => $p->id, 'warehouse_id' => $w->id,
            'quantity' => $qty, 'reserved_quantity' => $reserved, 'average_cost' => '10.0000', 'min_stock' => $min, 'max_stock' => $max,
        ])->save();

        return $s;
    }

    private function seedAssignment(object $t, Warehouse $w, User $keeper): void
    {
        $a = new WarehouseAssignment;
        $a->forceFill(['business_id' => $t->business->id, 'branch_id' => $w->branch_id, 'warehouse_id' => $w->id, 'user_id' => $keeper->id, 'assigned_by' => $t->admin->id, 'assigned_at' => now()])->save();
    }

    // ===================== (1) Unidad de medida legible =====================

    public function test_stock_incluye_unidad_legible_para_bodeguero_asignado(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '10.000');
        $this->seedAssignment($t, $t->whA1, $t->bodA);

        // Índice.
        $this->asUser($t->bodA)->getJson('/api/v1/stock')->assertOk()
            ->assertJsonPath('data.0.product.unit.id', $t->unit->id)
            ->assertJsonPath('data.0.product.unit.abbreviation', $t->unit->abbreviation)
            ->assertJsonPath('data.0.product.unit.name', 'Kilogramo');

        // Detalle.
        $this->asUser($t->bodA)->getJson("/api/v1/stock/{$p->id}/{$t->whA1->id}")->assertOk()
            ->assertJsonPath('data.product.unit.abbreviation', $t->unit->abbreviation);
    }

    public function test_conteo_incluye_unidad_al_registrar_y_al_listar(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '10.000');
        $this->seedAssignment($t, $t->whA1, $t->bodA);

        // El conteo recién registrado ya trae la unidad.
        $this->asUser($t->bodA)->postJson('/api/v1/physical-counts', [
            'warehouse_id' => $t->whA1->id, 'product_id' => $p->id, 'counted_quantity' => '9.000',
        ])->assertCreated()
            ->assertJsonPath('data.product.unit.abbreviation', $t->unit->abbreviation)
            ->assertJsonPath('data.product.unit.name', 'Kilogramo');

        // Y el índice de conteos también.
        $this->asUser($t->bodA)->getJson('/api/v1/physical-counts')->assertOk()
            ->assertJsonPath('data.0.product.unit.abbreviation', $t->unit->abbreviation);
    }

    // ===================== (2) PUT thresholds (antes 500) =====================

    public function test_actualiza_umbrales_200_y_persiste_sin_tocar_existencia(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '7.000', '2.000');

        $this->asUser($t->admin)->putJson("/api/v1/stock/{$p->id}/{$t->whA1->id}/thresholds", [
            'min_stock' => '4.000', 'max_stock' => '10.000',
        ])->assertOk()
            ->assertJsonPath('data.min_stock', '4.000')
            ->assertJsonPath('data.max_stock', '10.000')
            // La respuesta también trae la unidad legible.
            ->assertJsonPath('data.product.unit.abbreviation', $t->unit->abbreviation);

        // Persistencia y no-mutación de existencia/reservado/costo.
        $stock = StockLevel::withoutGlobalScopes()->where('product_id', $p->id)->where('warehouse_id', $t->whA1->id)->firstOrFail();
        $this->assertSame('4.000', (string) $stock->min_stock);
        $this->assertSame('10.000', (string) $stock->max_stock);
        $this->assertSame('7.000', (string) $stock->quantity);
        $this->assertSame('2.000', (string) $stock->reserved_quantity);
        $this->assertSame('10.0000', (string) $stock->average_cost);
    }

    public function test_umbrales_invalidos_min_mayor_que_max_devuelve_422(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '7.000');

        $this->asUser($t->admin)->putJson("/api/v1/stock/{$p->id}/{$t->whA1->id}/thresholds", [
            'min_stock' => '10.000', 'max_stock' => '4.000',
        ])->assertStatus(422)->assertJsonValidationErrors(['min_stock']);

        // No se persistió ningún umbral.
        $stock = StockLevel::withoutGlobalScopes()->where('product_id', $p->id)->where('warehouse_id', $t->whA1->id)->firstOrFail();
        $this->assertNull($stock->min_stock);
        $this->assertNull($stock->max_stock);
    }

    public function test_umbrales_solo_administrativo_y_aislado_por_negocio(): void
    {
        // Ambos negocios se siembran por adelantado (patrón de los demás tests multinegocio).
        $t = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '7.000');
        $this->seedAssignment($t, $t->whA1, $t->bodA);

        // ROL-03 (aunque tenga la bodega asignada) NO fija umbrales: es potestad administrativa (ROL-02+).
        $this->asUser($t->bodA)->putJson("/api/v1/stock/{$p->id}/{$t->whA1->id}/thresholds", [
            'min_stock' => '4.000', 'max_stock' => '10.000',
        ])->assertStatus(403);

        // Aislamiento de negocio: el admin de otro negocio no resuelve el saldo (BusinessScope → 404).
        $this->asUser($b->admin)->putJson("/api/v1/stock/{$p->id}/{$t->whA1->id}/thresholds", [
            'min_stock' => '4.000', 'max_stock' => '10.000',
        ])->assertNotFound();

        $stock = StockLevel::withoutGlobalScopes()->where('product_id', $p->id)->where('warehouse_id', $t->whA1->id)->firstOrFail();
        $this->assertNull($stock->min_stock);
    }

    // ===================== Aceptación: /stock/alerts =====================

    public function test_alerts_no_vacio_cuando_disponible_bajo_minimo_incluida_reduccion_por_reserva(): void
    {
        $t = $this->seedTenant('a');
        $pReserva = $this->makeProduct($t); // qty 10, reserved 7 → disponible 3 ≤ min 5 → AVISA
        $pAlto    = $this->makeProduct($t); // qty 10, reserved 0 → disponible 10 > min 5 → no avisa
        $this->seedStock($t, $t->whA1, $pReserva, '10.000', '7.000', '5.000');
        $this->seedStock($t, $t->whA1, $pAlto, '10.000', '0.000', '5.000');

        $data = $this->asUser($t->admin)->getJson('/api/v1/stock/alerts')->assertOk()->json('data');
        $ids = array_map(static fn ($r) => (int) $r['product_id'], $data);

        $this->assertNotEmpty($data);
        $this->assertContains($pReserva->id, $ids); // existencia sobre el mínimo, pero la reserva lo deja bajo.
        $this->assertNotContains($pAlto->id, $ids);
    }
}
