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
 * MOD-03 - Asignacion Bodega-Bodeguero (M:N, historial temporal) + enforcement en flujos de inventario.
 * Un bodeguero con varias bodegas; una bodega con varios bodegueros; misma sucursal; perfil bodeguero;
 * operar (conteo/ajuste/traspaso) solo bodegas asignadas; conflictos; aislamiento sucursal/negocio.
 */
final class WarehouseAssignmentHttpTest extends MysqlTestCase
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
        $bodA = $this->makeUser($business, RoleName::Operator, $branchA, 'bodeguero');
        $bodA2 = $this->makeUser($business, RoleName::Operator, $branchA, 'bodeguero');
        $bodB = $this->makeUser($business, RoleName::Operator, $branchB, 'bodeguero');
        $cajero = $this->makeUser($business, RoleName::Operator, $branchA, 'cajero');

        $category = new Category(['name' => 'Cat '.self::$seq]);
        $category->business_id = $business->id;
        $category->save();
        $unit = new UnitOfMeasure(['name' => 'u', 'abbreviation' => 'u'.self::$seq]);
        $unit->business_id = $business->id;
        $unit->save();
        $product = new Product([
            'category_id' => $category->id, 'unit_id' => $unit->id, 'sku' => 'SKU-'.self::$seq,
            'name' => 'P', 'type' => ProductType::Simple, 'sale_price' => '20.00', 'cost' => '10.00',
            'tracks_inventory' => true, 'tax_class' => TaxClass::Standard->value, 'is_active' => true,
        ]);
        $product->business_id = $business->id;
        $product->save();

        $this->activateBusinessSubscription($business->id);

        return (object) compact('business', 'branchA', 'branchB', 'whA1', 'whA2', 'whB1', 'owner', 'admin', 'bodA', 'bodA2', 'bodB', 'cajero', 'product');
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

    private function seedAssignment(object $t, Warehouse $w, User $keeper): WarehouseAssignment
    {
        $a = new WarehouseAssignment;
        $a->forceFill(['business_id' => $t->business->id, 'branch_id' => $w->branch_id, 'warehouse_id' => $w->id, 'user_id' => $keeper->id, 'assigned_by' => $t->admin->id, 'assigned_at' => now()])->save();

        return $a->refresh();
    }

    private function seedStock(object $t, Warehouse $w, string $qty): void
    {
        $s = new StockLevel;
        $s->forceFill(['business_id' => $t->business->id, 'product_id' => $t->product->id, 'warehouse_id' => $w->id, 'quantity' => $qty, 'reserved_quantity' => '0.000', 'average_cost' => '10.0000'])->save();
    }

    private function assign(object $t, User $actor, Warehouse $w, User $keeper)
    {
        return $this->asUser($actor)->postJson('/api/v1/warehouse-assignments', ['warehouse_id' => $w->id, 'user_id' => $keeper->id]);
    }

    private function countAt(User $actor, Warehouse $w, object $t)
    {
        return $this->asUser($actor)->postJson('/api/v1/physical-counts', ['warehouse_id' => $w->id, 'product_id' => $t->product->id, 'counted_quantity' => '1.000']);
    }

    private function adjust(User $actor, Warehouse $w, object $t)
    {
        return $this->asUser($actor)->postJson('/api/v1/inventory-adjustments', [
            'warehouse_id' => $w->id, 'product_id' => $t->product->id, 'type' => 'sobrante', 'quantity' => '1.000', 'reason' => 'ajuste demo',
        ]);
    }

    // ---------------- Alta / autorizacion ----------------

    public function test_admin_asigna_bodega_a_bodeguero(): void
    {
        $t = $this->seedTenant('a');
        $this->assign($t, $t->admin, $t->whA1, $t->bodA)
            ->assertCreated()->assertJsonPath('data.active', true)->assertJsonPath('data.warehouse_id', $t->whA1->id);
    }

    public function test_cajero_no_puede_asignar(): void
    {
        $t = $this->seedTenant('a');
        $this->assign($t, $t->cajero, $t->whA1, $t->bodA)->assertStatus(403);
    }

    public function test_usuario_no_bodeguero_rechazado_422(): void
    {
        $t = $this->seedTenant('a');
        $this->assign($t, $t->admin, $t->whA1, $t->cajero)->assertStatus(422)->assertJsonValidationErrors(['user_id']);
    }

    public function test_misma_sucursal_obligatoria_422(): void
    {
        $t = $this->seedTenant('a');
        // bodA (sucursal A) a whB1 (sucursal B) -> 422.
        $this->assign($t, $t->admin, $t->whB1, $t->bodA)->assertStatus(422)->assertJsonValidationErrors(['user_id']);
    }

    // ---------------- M:N ----------------

    public function test_bodeguero_con_varias_bodegas_y_bodega_con_varios_bodegueros(): void
    {
        $t = $this->seedTenant('a');
        // Un bodeguero (bodA) con DOS bodegas activas.
        $this->assign($t, $t->admin, $t->whA1, $t->bodA)->assertCreated();
        $this->assign($t, $t->admin, $t->whA2, $t->bodA)->assertCreated();
        // Una bodega (whA1) con DOS bodegueros activos.
        $this->assign($t, $t->admin, $t->whA1, $t->bodA2)->assertCreated();

        $this->assertSame(2, WarehouseAssignment::withoutGlobalScopes()->where('user_id', $t->bodA->id)->whereNull('ended_at')->count());
        $this->assertSame(2, WarehouseAssignment::withoutGlobalScopes()->where('warehouse_id', $t->whA1->id)->whereNull('ended_at')->count());
    }

    public function test_par_activo_duplicado_devuelve_409(): void
    {
        $t = $this->seedTenant('a');
        $this->seedAssignment($t, $t->whA1, $t->bodA);
        $this->assign($t, $t->admin, $t->whA1, $t->bodA)->assertStatus(409)->assertJsonPath('error', 'WAREHOUSE_ALREADY_ASSIGNED');
    }

    // ---------------- Finalizacion / historial ----------------

    public function test_finalizar_y_reasignar_conserva_historial(): void
    {
        $t = $this->seedTenant('a');
        $first = $this->seedAssignment($t, $t->whA1, $t->bodA);

        $this->asUser($t->admin)->deleteJson("/api/v1/warehouse-assignments/{$first->id}")->assertOk()->assertJsonPath('data.active', false);
        // Reasignar el mismo par tras finalizar -> nueva fila activa.
        $this->assign($t, $t->admin, $t->whA1, $t->bodA)->assertCreated();

        $this->assertSame(2, WarehouseAssignment::withoutGlobalScopes()->where('warehouse_id', $t->whA1->id)->where('user_id', $t->bodA->id)->count());
        $this->assertNotNull($first->refresh()->ended_at);
    }

    // ---------------- Enforcement en flujos (operar solo bodega asignada) ----------------

    public function test_conteo_solo_en_bodega_asignada(): void
    {
        $t = $this->seedTenant('a');

        // Sin asignacion -> 403.
        $this->countAt($t->bodA, $t->whA1, $t)->assertStatus(403);

        // Con asignacion -> 201.
        $this->seedAssignment($t, $t->whA1, $t->bodA);
        $this->countAt($t->bodA, $t->whA1, $t)->assertCreated();
    }

    public function test_ajuste_es_potestad_administrativa_no_de_bodeguero(): void
    {
        $t = $this->seedTenant('a');
        $this->seedStock($t, $t->whA1, '5.000');
        $this->seedAssignment($t, $t->whA1, $t->bodA); // incluso asignado...

        // ...un bodeguero NO ajusta inventario: su perfil no habilita 'ajustes' (403 por Policy). No se
        // reutiliza un permiso por conveniencia; la compuerta de asignacion es defensa en profundidad.
        $this->adjust($t->bodA, $t->whA1, $t)->assertStatus(403);

        // El ajuste es potestad administrativa (ROL-02+).
        $this->adjust($t->admin, $t->whA1, $t)->assertCreated();
    }

    public function test_apply_de_conteo_fisico_persiste_ajuste_con_user_id(): void
    {
        $t = $this->seedTenant('a');
        $this->seedStock($t, $t->whA1, '5.000');

        // Admin crea un conteo con diferencia (contado 8 vs sistema 5).
        $countId = $this->asUser($t->admin)->postJson('/api/v1/physical-counts', [
            'warehouse_id' => $t->whA1->id, 'product_id' => $t->product->id, 'counted_quantity' => '8.000',
        ])->assertCreated()->json('data.id');

        // Aplicar el conteo recorre InventoryService::ajustarPorConteo: genera el ajuste por correccion.
        $this->asUser($t->admin)->postJson("/api/v1/physical-counts/{$countId}/apply")
            ->assertOk()->assertJsonPath('data.status', 'ajustado');

        // El ajuste SE PERSISTE con user_id del actor (antes fallaba por 1364: user_id ausente en el INSERT).
        $this->assertDatabaseHas('inventory_adjustments', [
            'physical_count_id' => $countId,
            'user_id'           => $t->admin->id,
        ]);
        // Y el saldo queda ajustado al conteo (8).
        $stock = StockLevel::withoutGlobalScopes()
            ->where('product_id', $t->product->id)->where('warehouse_id', $t->whA1->id)->firstOrFail();
        $this->assertSame('8.000', (string) $stock->quantity);
    }

    public function test_bodega_de_su_sucursal_pero_no_asignada_rechazada(): void
    {
        $t = $this->seedTenant('a');
        // bodA asignado a whA1 pero NO a whA2 (misma sucursal A).
        $this->seedAssignment($t, $t->whA1, $t->bodA);

        $this->countAt($t->bodA, $t->whA2, $t)->assertStatus(403); // misma sucursal, sin asignacion.
        $this->countAt($t->bodA, $t->whA1, $t)->assertCreated();
    }

    public function test_bodega_de_otra_sucursal_rechazada_422(): void
    {
        $t = $this->seedTenant('a');
        // whB1 es de otra sucursal: el FormRequest (after) rechaza por sucursal antes del servicio.
        $this->countAt($t->bodA, $t->whB1, $t)->assertStatus(422)->assertJsonValidationErrors(['warehouse_id']);
    }

    public function test_traspaso_exige_asignacion_en_origen(): void
    {
        $t = $this->seedTenant('a');
        $this->seedStock($t, $t->whA1, '5.000');

        $payload = ['from_warehouse_id' => $t->whA1->id, 'to_warehouse_id' => $t->whA2->id, 'items' => [['product_id' => $t->product->id, 'quantity' => '1.000']]];

        // Sin asignacion al origen -> 403.
        $this->asUser($t->bodA)->postJson('/api/v1/stock-transfers', $payload)->assertStatus(403);

        // Asignado a origen y destino -> crea y completa.
        $this->seedAssignment($t, $t->whA1, $t->bodA);
        $this->seedAssignment($t, $t->whA2, $t->bodA);
        $id = $this->asUser($t->bodA)->postJson('/api/v1/stock-transfers', $payload)->assertCreated()->json('data.id');
        $this->asUser($t->bodA)->postJson("/api/v1/stock-transfers/{$id}/complete")->assertOk()->assertJsonPath('data.status', 'completado');
    }

    public function test_admin_opera_cualquier_bodega_sin_asignacion(): void
    {
        $t = $this->seedTenant('a');
        $this->seedStock($t, $t->whB1, '5.000');
        // ROL-02 no requiere asignacion (autoridad del rol humano): opera bodegas de cualquier sucursal.
        $this->countAt($t->admin, $t->whA1, $t)->assertCreated();
        $this->adjust($t->admin, $t->whB1, $t)->assertCreated();
    }

    // ---------------- Listado / aislamiento ----------------

    public function test_rol03_lista_solo_sus_asignaciones(): void
    {
        $t = $this->seedTenant('a');
        $this->seedAssignment($t, $t->whA1, $t->bodA);
        $this->seedAssignment($t, $t->whA2, $t->bodA2);

        $data = $this->asUser($t->bodA)->getJson('/api/v1/warehouse-assignments')->assertOk()->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($t->bodA->id, $data[0]['user_id']);

        $this->assertCount(2, $this->asUser($t->admin)->getJson('/api/v1/warehouse-assignments')->assertOk()->json('data'));
    }

    public function test_finalizar_asignacion_de_otro_negocio_404(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $assignment = $this->seedAssignment($a, $a->whA1, $a->bodA);

        $this->asUser($b->admin)->deleteJson("/api/v1/warehouse-assignments/{$assignment->id}")->assertNotFound();
        $this->assertNull($assignment->refresh()->ended_at);
    }

    public function test_bodeguero_de_otro_negocio_no_opera(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        // bodeguero de B intenta contar una bodega de A -> BusinessScope oculta la bodega del otro negocio.
        $resp = $this->countAt($b->bodA, $a->whA1, $a);
        $this->assertContains($resp->status(), [403, 404, 422]);
    }
}
