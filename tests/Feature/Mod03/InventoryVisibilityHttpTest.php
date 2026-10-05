<?php

declare(strict_types=1);

namespace Tests\Feature\Mod03;

use App\Enums\ProductType;
use App\Enums\RoleName;
use App\Enums\TaxClass;
use App\Models\Anomaly;
use App\Models\AnomalyRule;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\PhysicalCount;
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
 * MOD-03 (microcierre) · Visibilidad de inventario lógico y bodega física.
 *
 * Cubre: (2) consulta consolidada por producto+bodega (existencia/reservado/disponible + último conteo con
 * fecha, saldo del sistema en ese momento, diferencia y estado de conciliación) con aislamiento de ROL-03 por
 * bodega ASIGNADA aun dentro de una misma sucursal; (3) la diferencia del conteo NO desaparece por estar bajo
 * el umbral de la anomalía; (5) aviso operativo de mínimo (disponible ≤ min), su recuperación, los datos de
 * reposición y su aislamiento por bodega/sucursal/negocio.
 */
final class InventoryVisibilityHttpTest extends MysqlTestCase
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
        $branchB = $this->makeBranch($business, 'SB');
        $whA1 = $this->makeWarehouse($business, $branchA, 'A1', true);
        $whA2 = $this->makeWarehouse($business, $branchA, 'A2', false);
        $whB1 = $this->makeWarehouse($business, $branchB, 'B1', true);

        $owner = $this->makeUser($business, RoleName::Owner);
        $admin = $this->makeUser($business, RoleName::Admin);
        $bodA  = $this->makeUser($business, RoleName::Operator, $branchA, 'bodeguero');
        $bodA2 = $this->makeUser($business, RoleName::Operator, $branchA, 'bodeguero');
        $bodB  = $this->makeUser($business, RoleName::Operator, $branchB, 'bodeguero');

        $category = new Category(['name' => 'Cat '.self::$seq]);
        $category->business_id = $business->id;
        $category->save();
        $unit = new UnitOfMeasure(['name' => 'u', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();

        return (object) compact('business', 'branchA', 'branchB', 'whA1', 'whA2', 'whB1', 'owner', 'admin', 'bodA', 'bodA2', 'bodB', 'category', 'unit');
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

    private function makeProduct(object $t, string $name = 'P'): Product
    {
        $p = new Product([
            'category_id' => $t->category->id, 'unit_id' => $t->unit->id, 'sku' => 'SKU-'.(++self::$seq),
            'name' => $name.' '.self::$seq, 'type' => ProductType::Simple, 'sale_price' => '20.00', 'cost' => '10.00',
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
            'quantity' => $qty, 'reserved_quantity' => $reserved, 'average_cost' => '10.0000',
            'min_stock' => $min, 'max_stock' => $max,
        ])->save();

        return $s;
    }

    private function seedAssignment(object $t, Warehouse $w, User $keeper): WarehouseAssignment
    {
        $a = new WarehouseAssignment;
        $a->forceFill(['business_id' => $t->business->id, 'branch_id' => $w->branch_id, 'warehouse_id' => $w->id, 'user_id' => $keeper->id, 'assigned_by' => $t->admin->id, 'assigned_at' => now()])->save();

        return $a->refresh();
    }

    /** Devuelve la fila de /stock cuyo product_id+warehouse_id coincide, o null. */
    private function rowFor(array $data, int $productId, int $warehouseId): ?array
    {
        foreach ($data as $row) {
            if ((int) $row['product_id'] === $productId && (int) $row['warehouse_id'] === $warehouseId) {
                return $row;
            }
        }

        return null;
    }

    // ===================== (2) Vista consolidada + último conteo =====================

    public function test_stock_index_incluye_ultimo_conteo_diferencia_y_estado(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '10.000');

        // Conteo: sistema 10 vs contado 8 → diferencia -2 (columna generada). No cambia la existencia.
        $this->asUser($t->admin)->postJson('/api/v1/physical-counts', [
            'warehouse_id' => $t->whA1->id, 'product_id' => $p->id, 'counted_quantity' => '8.000',
        ])->assertCreated();

        $row = $this->rowFor($this->asUser($t->admin)->getJson('/api/v1/stock')->assertOk()->json('data'), $p->id, $t->whA1->id);

        $this->assertNotNull($row);
        $this->assertSame('10.000', $row['quantity']);            // existencia registrada (sin tocar)
        $this->assertSame('0.000', $row['reserved_quantity']);
        $this->assertSame('10.000', $row['available']);
        // El último conteo es un bloque SEPARADO: no se presenta como existencia actual.
        $this->assertSame('8.000', $row['last_count']['counted_quantity']);
        $this->assertSame('10.000', $row['last_count']['system_quantity']);
        $this->assertSame('-2.000', $row['last_count']['difference']);
        $this->assertSame('abierto', $row['last_count']['status']);
        $this->assertNotNull($row['last_count']['counted_at']);
    }

    public function test_stock_sin_conteos_expone_last_count_nulo(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '5.000');

        $row = $this->rowFor($this->asUser($t->admin)->getJson('/api/v1/stock')->assertOk()->json('data'), $p->id, $t->whA1->id);
        $this->assertNull($row['last_count']);
    }

    public function test_rol03_solo_ve_existencias_de_bodegas_asignadas_misma_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        // Dos bodegas de la MISMA sucursal (A) con existencia del mismo producto.
        $this->seedStock($t, $t->whA1, $p, '10.000');
        $this->seedStock($t, $t->whA2, $p, '20.000');

        // bodA asignado SOLO a whA1 (no a whA2, aunque ambas son de la sucursal A).
        $this->seedAssignment($t, $t->whA1, $t->bodA);

        $data = $this->asUser($t->bodA)->getJson('/api/v1/stock')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($t->whA1->id, (int) $data[0]['warehouse_id']);

        // El admin ve ambas bodegas (alcance de negocio).
        $this->assertCount(2, $this->asUser($t->admin)->getJson('/api/v1/stock')->assertOk()->json('data'));
    }

    public function test_rol03_sin_asignacion_no_ve_existencias(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '10.000');

        $this->asUser($t->bodA)->getJson('/api/v1/stock')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_rol03_show_de_bodega_no_asignada_403(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '10.000');
        $this->seedStock($t, $t->whA2, $p, '20.000');
        $this->seedAssignment($t, $t->whA1, $t->bodA);

        $this->asUser($t->bodA)->getJson("/api/v1/stock/{$p->id}/{$t->whA1->id}")->assertOk();
        $this->asUser($t->bodA)->getJson("/api/v1/stock/{$p->id}/{$t->whA2->id}")->assertStatus(403);
    }

    public function test_rol03_conteos_solo_de_bodegas_asignadas(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '10.000');
        $this->seedStock($t, $t->whA2, $p, '10.000');
        // Un conteo en cada bodega de la sucursal A.
        $this->asUser($t->admin)->postJson('/api/v1/physical-counts', ['warehouse_id' => $t->whA1->id, 'product_id' => $p->id, 'counted_quantity' => '9.000'])->assertCreated();
        $this->asUser($t->admin)->postJson('/api/v1/physical-counts', ['warehouse_id' => $t->whA2->id, 'product_id' => $p->id, 'counted_quantity' => '9.000'])->assertCreated();

        $this->seedAssignment($t, $t->whA1, $t->bodA);

        $data = $this->asUser($t->bodA)->getJson('/api/v1/physical-counts')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($t->whA1->id, (int) $data[0]['warehouse_id']);
    }

    // ===================== (3) La diferencia no desaparece por umbral =====================

    public function test_diferencia_de_conteo_visible_aunque_bajo_umbral_sin_anomalia(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t);
        $this->seedStock($t, $t->whA1, $p, '10.000');

        // Umbral de la regla ALTO (100): un faltante pequeño no genera anomalía...
        AnomalyRule::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('code', 'faltante_inventario')
            ->update(['threshold_value' => '100.00', 'is_active' => true]);

        // Faltante de 2 (sistema 10, contado 8).
        $this->asUser($t->admin)->postJson('/api/v1/physical-counts', [
            'warehouse_id' => $t->whA1->id, 'product_id' => $p->id, 'counted_quantity' => '8.000',
        ])->assertCreated();

        // Corrida de conciliación de inventario: NO levanta anomalía (bajo umbral).
        $this->asUser($t->admin)->postJson('/api/v1/reconciliation-runs', ['scope' => 'inventario_bodega'])
            ->assertCreated()->assertJsonPath('data.anomalies_found', 0);

        $this->assertSame(0, Anomaly::withoutGlobalScopes()->where('business_id', $t->business->id)->count());

        // ...pero la DIFERENCIA del conteo sigue visible en la vista consolidada (no desaparece).
        $row = $this->rowFor($this->asUser($t->admin)->getJson('/api/v1/stock')->assertOk()->json('data'), $p->id, $t->whA1->id);
        $this->assertSame('-2.000', $row['last_count']['difference']);
        $this->assertSame('abierto', $row['last_count']['status']);
    }

    // ===================== (5) Aviso operativo de mínimo =====================

    public function test_aviso_minimo_disponible_bajo_minimo_y_respeta_reservas(): void
    {
        $t = $this->seedTenant('a');
        $pBajo  = $this->makeProduct($t, 'Bajo');   // disponible 4 ≤ min 5 → avisa
        $pAlto  = $this->makeProduct($t, 'Alto');   // disponible 10 > min 5 → no avisa
        $pResva = $this->makeProduct($t, 'Reserva');// disponible 3 ≤ min 5 por reserva → avisa
        $pSinMin = $this->makeProduct($t, 'SinMin');// sin mínimo → no se inventa

        $this->seedStock($t, $t->whA1, $pBajo,  '4.000',  '0.000', '5.000');
        $this->seedStock($t, $t->whA1, $pAlto,  '10.000', '0.000', '5.000');
        $this->seedStock($t, $t->whA1, $pResva, '10.000', '7.000', '5.000');
        $this->seedStock($t, $t->whA1, $pSinMin, '2.000', '0.000', null);

        $data  = $this->asUser($t->admin)->getJson('/api/v1/stock/alerts')->assertOk()->json('data');
        $ids   = array_map(static fn ($r) => (int) $r['product_id'], $data);

        $this->assertContains($pBajo->id, $ids);
        $this->assertContains($pResva->id, $ids);
        $this->assertNotContains($pAlto->id, $ids);
        $this->assertNotContains($pSinMin->id, $ids);
    }

    public function test_aviso_minimo_incluye_datos_reposicion_y_permiso_compras(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t, 'Repo');
        // disponible 4, min 5, max 12 → sugerido = 12 - 4 = 8.
        $this->seedStock($t, $t->whA1, $p, '4.000', '0.000', '5.000', '12.000');

        $resp = $this->asUser($t->admin)->getJson('/api/v1/stock/alerts')->assertOk()
            ->assertJsonPath('meta.can_request_purchase', true);

        $row = $this->rowFor($resp->json('data'), $p->id, $t->whA1->id);
        $this->assertSame('4.000', $row['available']);
        $this->assertSame('5.000', $row['min_stock']);
        $this->assertSame($t->whA1->id, (int) $row['replenishment']['warehouse_id']);
        $this->assertSame($t->branchA->id, (int) $row['replenishment']['branch_id']);
        $this->assertSame($p->id, (int) $row['replenishment']['product_id']);
        $this->assertSame('8.000', $row['replenishment']['suggested_quantity']);

        // El bodeguero (perfil con compras.crear) también puede iniciar la reposición.
        $this->seedAssignment($t, $t->whA1, $t->bodA);
        $this->asUser($t->bodA)->getJson('/api/v1/stock/alerts')->assertOk()
            ->assertJsonPath('meta.can_request_purchase', true);
    }

    public function test_aviso_minimo_recuperacion_al_subir_existencia(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t, 'Recup');
        $stock = $this->seedStock($t, $t->whA1, $p, '4.000', '0.000', '5.000');

        // Bajo mínimo → avisa.
        $ids = array_map(static fn ($r) => (int) $r['product_id'], $this->asUser($t->admin)->getJson('/api/v1/stock/alerts')->json('data'));
        $this->assertContains($p->id, $ids);

        // Reposición: sube la existencia por encima del mínimo → el aviso (derivado) desaparece solo.
        $stock->forceFill(['quantity' => '20.000'])->save();

        $ids = array_map(static fn ($r) => (int) $r['product_id'], $this->asUser($t->admin)->getJson('/api/v1/stock/alerts')->json('data'));
        $this->assertNotContains($p->id, $ids);
    }

    public function test_aviso_minimo_aislamiento_por_bodega_asignada_y_sucursal(): void
    {
        $t = $this->seedTenant('a');
        $p = $this->makeProduct($t, 'Iso');
        // Bajo mínimo en whA1 (asignada), whA2 (misma sucursal, NO asignada) y whB1 (otra sucursal).
        $this->seedStock($t, $t->whA1, $p, '1.000', '0.000', '5.000');
        $this->seedStock($t, $t->whA2, $p, '1.000', '0.000', '5.000');
        $this->seedStock($t, $t->whB1, $p, '1.000', '0.000', '5.000');

        $this->seedAssignment($t, $t->whA1, $t->bodA);

        $data = $this->asUser($t->bodA)->getJson('/api/v1/stock/alerts')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($t->whA1->id, (int) $data[0]['warehouse_id']);

        // El admin ve las tres bodegas.
        $this->assertCount(3, $this->asUser($t->admin)->getJson('/api/v1/stock/alerts')->assertOk()->json('data'));
    }

    public function test_aviso_minimo_aislamiento_entre_negocios(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $pa = $this->makeProduct($a, 'A');
        $this->seedStock($a, $a->whA1, $pa, '1.000', '0.000', '5.000');

        // El negocio B no ve los avisos de A (BusinessScope).
        $this->asUser($b->admin)->getJson('/api/v1/stock/alerts')->assertOk()->assertJsonCount(0, 'data');
    }
}
