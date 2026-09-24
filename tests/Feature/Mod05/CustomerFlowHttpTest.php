<?php

declare(strict_types=1);

namespace Tests\Feature\Mod05;

use App\Enums\RoleName;
use App\Models\AccountReceivable;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-05 - Pruebas HTTP end-to-end contra MySQL (datos controlados, revertidos).
 */
final class CustomerFlowHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    private function asUser(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        // Recarga desde MySQL para el guard web. whereKey()->firstOrFail() + instanceof
        // estrecha el tipo al contrato Authenticatable que exige actingAs() (findOrFail
        // conservaba la unión User|Collection y disparaba P1006 en Intelephense).
        $authenticatedUser = User::query()->whereKey($u->getKey())->firstOrFail();

        if (! $authenticatedUser instanceof AuthenticatableContract) {
            throw new \RuntimeException('El usuario recargado no implementa Authenticatable.');
        }

        return $this->actingAs($authenticatedUser, 'web');
    }

    /** Negocio sembrado NORMALMENTE (con observer: cliente genérico + roles). */
    private function seedTenant(string $slug): object
    {
        // La siembra debe ser independiente del contexto de autenticación: si queda
        // un usuario logueado de otro negocio (p. ej. tras un asUser previo en el
        // mismo test), su BusinessScope contamina la re-consulta de recuperación de
        // createOrFirst en BusinessObserver y rompe la idempotencia del genérico.
        $this->app['auth']->forgetGuards();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        app(RolesAndPermissionsSeeder::class)->run();

        $business = Business::create([
            'name'     => 'Negocio '.$slug,
            'slug'     => $slug.'-'.(++self::$seq),
            'plan'     => 'basic',
            'status'   => 'active',
            'tax_rate' => '0.1500',
            'timezone' => 'America/Managua',
        ]);

        $owner    = $this->makeUser($business, RoleName::Owner);
        $admin    = $this->makeUser($business, RoleName::Admin);
        $operator = $this->makeUser($business, RoleName::Operator);

        // Sucursal explícita: la CxC real exige factura (invoice_id NOT NULL) y toda
        // factura exige branch_id. Es la dependencia imprescindible del guarda CxC.
        $branch = new Branch();
        $branch->forceFill([
            'business_id' => $business->id,
            'name'        => 'Sucursal '.$slug,
            'address'     => 'Dir. '.$slug,
            'opened_at'   => now()->toDateString(),
            'is_active'   => true,
        ])->saveQuietly();

        $generic = Customer::withoutGlobalScopes()
            ->where('business_id', $business->id)->where('is_generic', true)->firstOrFail();

        return (object) compact('business', 'owner', 'admin', 'operator', 'branch', 'generic');
    }

    /**
     * Crea una Cuenta por Cobrar REAL en `accounts_receivables` (nombre físico de
     * tabla, confirmado en AccountReceivable::$table). Cada CxC cuelga de una
     * factura real (invoice_id NOT NULL). El `status` no es fillable (lo fija el
     * Service/cron), así que se escribe con forceFill para reproducir cada estado.
     * `balance` es columna generada por el motor: nunca se escribe.
     */
    private function makeReceivable(object $t, int $customerId, string $status): AccountReceivable
    {
        $invoice = new Invoice();
        $invoice->forceFill([
            'business_id'     => $t->business->id,
            'branch_id'       => $t->branch->id,
            'customer_id'     => $customerId,
            'issued_by'       => $t->owner->id,
            'folio'           => 'F-'.(++self::$seq),
            'payment_type'    => 'credito',
            'payment_status'  => 'pendiente',
            'status'          => 'emitida',
            'subtotal'        => '100.00',
            'tax_amount'      => '0.00',
            'discount_amount' => '0.00',
            'total'           => '100.00',
            'paid_amount'     => '0.00',
            'issued_at'       => now(),
        ])->saveQuietly();

        // total_amount fijo en 100; paid_amount/due_date definen el estado.
        [$paid, $due] = match ($status) {
            'pendiente' => ['0.00',   null],
            'parcial'   => ['40.00',  null],
            'vencida'   => ['0.00',   now()->subDays(10)->toDateString()],
            'pagada'    => ['100.00', null],
        };

        $ar = new AccountReceivable();
        $ar->forceFill([
            'business_id'  => $t->business->id,
            'customer_id'  => $customerId,
            'invoice_id'   => $invoice->id,
            'total_amount' => '100.00',
            'paid_amount'  => $paid,
            'status'       => $status,
            'due_date'     => $due,
        ])->saveQuietly();

        return $ar;
    }

    private function makeUser(Business $business, RoleName $role): User
    {
        $user = new User([
            'name'      => $role->value.' '.(++self::$seq),
            'email'     => 'u'.self::$seq.'@test.local',
            'password'  => Hash::make('secret-Password-123'),
            'is_active' => true,
        ]);
        $user->business_id = $business->id;
        $user->save();

        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $user->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $user;
    }

    /** Payload de cliente válido. */
    private function customerPayload(array $overrides = []): array
    {
        return array_merge([
            'name'            => 'Cliente '.(++self::$seq),
            'document_type'   => 'cedula',
            'document_number' => 'DOC-'.self::$seq,
            'email'           => 'c'.self::$seq.'@test.local',
            'credit_limit'    => '0.00',
        ], $overrides);
    }

    // -----------------------------------------------------------------------

    public function test_operador_crea_cliente_y_listado_excluye_generico(): void
    {
        $t = $this->seedTenant('a');

        $created = $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload());
        $created->assertCreated()->assertJsonPath('data.is_generic', false);
        $customerId = $created->json('data.id');

        // Por defecto excluye el genérico (scopeReal).
        $list = $this->asUser($t->operator)->getJson('/api/v1/customers');
        $list->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains($customerId, $ids);
        $this->assertNotContains($t->generic->id, $ids, 'El genérico no debe listarse por defecto.');

        // include_generic=1 lo incluye (boolean estricto: 1/0, no "true"/"false").
        $listG = $this->asUser($t->operator)->getJson('/api/v1/customers?include_generic=1');
        $listG->assertOk();
        $this->assertContains($t->generic->id, collect($listG->json('data'))->pluck('id')->all());
    }

    public function test_show_incluye_direcciones(): void
    {
        $t = $this->seedTenant('a');
        $customerId = $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');

        $this->asUser($t->operator)->postJson("/api/v1/customers/{$customerId}/addresses", [
            'label'        => 'Casa',
            'address_line' => 'Calle 1',
            'is_default'   => true,
        ])->assertCreated();

        $this->asUser($t->operator)->getJson("/api/v1/customers/{$customerId}")
            ->assertOk()
            ->assertJsonPath('data.id', $customerId)
            ->assertJsonCount(1, 'data.addresses')
            ->assertJsonPath('data.addresses.0.label', 'Casa');
    }

    public function test_documento_duplicado_devuelve_422(): void
    {
        $t = $this->seedTenant('a');
        $payload = $this->customerPayload(['document_number' => 'DUP-1']);

        $this->asUser($t->operator)->postJson('/api/v1/customers', $payload)->assertCreated();

        $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload(['document_number' => 'DUP-1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document_number']);
    }

    public function test_generico_no_editable_ni_eliminable(): void
    {
        $t = $this->seedTenant('a');

        $this->asUser($t->admin)
            ->putJson("/api/v1/customers/{$t->generic->id}", ['name' => 'Hackeado'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'PROTECTED_RESOURCE');

        $this->asUser($t->admin)
            ->deleteJson("/api/v1/customers/{$t->generic->id}")
            ->assertStatus(403)
            ->assertJsonPath('code', 'PROTECTED_RESOURCE');
    }

    public function test_admin_actualiza_y_soft_delete(): void
    {
        $t = $this->seedTenant('a');
        $customerId = $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');

        $this->asUser($t->admin)->putJson("/api/v1/customers/{$customerId}", ['name' => 'Nombre Nuevo'])
            ->assertOk()->assertJsonPath('data.name', 'Nombre Nuevo');

        $this->asUser($t->admin)->deleteJson("/api/v1/customers/{$customerId}")->assertNoContent();

        $this->assertDatabaseHas('customers', ['id' => $customerId]); // sigue existiendo (soft)
        $this->assertNotNull(
            Customer::withoutGlobalScopes()->withTrashed()->find($customerId)->deleted_at
        );
    }

    public function test_operador_no_puede_actualizar_ni_eliminar(): void
    {
        $t = $this->seedTenant('a');
        $customerId = $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');

        // update/delete son ROL-02.
        $this->asUser($t->operator)->putJson("/api/v1/customers/{$customerId}", ['name' => 'X'])->assertStatus(403);
        $this->asUser($t->operator)->deleteJson("/api/v1/customers/{$customerId}")->assertStatus(403);
    }

    public function test_filtros_search_document_type_is_active(): void
    {
        $t = $this->seedTenant('a');

        $ana = $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload([
            'name' => 'Ana Perez', 'document_type' => 'ruc', 'document_number' => 'RUC-1',
        ]))->json('data.id');
        $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload([
            'name' => 'Beto Gomez', 'document_type' => 'cedula', 'document_number' => 'CED-1',
        ]))->assertCreated();

        // search por nombre.
        $this->asUser($t->operator)->getJson('/api/v1/customers?search=Ana')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ana);

        // document_type=ruc.
        $this->asUser($t->operator)->getJson('/api/v1/customers?document_type=ruc')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ana);

        // is_active=0 -> ninguno (todos activos). Boolean estricto: 1/0.
        $this->asUser($t->operator)->getJson('/api/v1/customers?is_active=0')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_direcciones_crud(): void
    {
        $t = $this->seedTenant('a');
        $customerId = $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');

        // index (revela si viewAny recibe el customer correctamente).
        $this->asUser($t->operator)->getJson("/api/v1/customers/{$customerId}/addresses")->assertOk();

        $addrId = $this->asUser($t->operator)->postJson("/api/v1/customers/{$customerId}/addresses", [
            'label' => 'Casa', 'address_line' => 'Calle 1',
        ])->assertCreated()->json('data.id');

        $this->asUser($t->operator)->putJson("/api/v1/customers/{$customerId}/addresses/{$addrId}", [
            'label' => 'Oficina',
        ])->assertOk()->assertJsonPath('data.label', 'Oficina');

        // DELETE es ROL-02 (fisico).
        $this->asUser($t->admin)->deleteJson("/api/v1/customers/{$customerId}/addresses/{$addrId}")->assertNoContent();
        $this->assertDatabaseMissing('customer_addresses', ['id' => $addrId]);
    }

    public function test_aislamiento_entre_dos_negocios(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');

        $customerA = $this->asUser($a->operator)->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');

        // B no ve el cliente de A.
        $this->asUser($b->operator)->getJson("/api/v1/customers/{$customerA}")->assertNotFound();
    }

    // =======================================================================
    // Microcierre MOD-05: guardas explícitas (CxC, binding anidado, genérico,
    // candado de documento, autorización ROL-03, paginación de direcciones).
    // =======================================================================

    public function test_guarda_cxc_bloquea_desactivacion_y_baja(): void
    {
        $t = $this->seedTenant('a');
        $customerId = $this->asUser($t->operator)
            ->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');

        // CxC reales VIVAS en la tabla física accounts_receivables.
        foreach (['pendiente', 'parcial', 'vencida'] as $st) {
            $ar = $this->makeReceivable($t, $customerId, $st);
            $this->assertDatabaseHas('accounts_receivables', ['id' => $ar->id, 'status' => $st]);
        }

        // Desactivar (is_active=false) un cliente con CxC viva => 422 codificado.
        $this->asUser($t->admin)->putJson("/api/v1/customers/{$customerId}", ['is_active' => false])
            ->assertStatus(422)->assertJsonPath('code', 'CUSTOMER_HAS_RECEIVABLES');

        // Borrado con CxC viva => mismo 422 codificado.
        $this->asUser($t->admin)->deleteJson("/api/v1/customers/{$customerId}")
            ->assertStatus(422)->assertJsonPath('code', 'CUSTOMER_HAS_RECEIVABLES');

        // Tras ambos rechazos el cliente sigue activo y NO borrado.
        $fresh = Customer::withoutGlobalScopes()->withTrashed()->findOrFail($customerId);
        $this->assertTrue((bool) $fresh->is_active, 'El cliente debe seguir activo.');
        $this->assertNull($fresh->deleted_at, 'El cliente NO debe quedar borrado.');
    }

    public function test_cxc_pagada_no_es_saldo_vivo(): void
    {
        $t = $this->seedTenant('a');
        $customerId = $this->asUser($t->operator)
            ->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');

        $ar = $this->makeReceivable($t, $customerId, 'pagada');
        $this->assertDatabaseHas('accounts_receivables', ['id' => $ar->id, 'status' => 'pagada']);

        // Una CxC pagada no pesa en la exposición: la baja procede (204).
        $this->asUser($t->admin)->deleteJson("/api/v1/customers/{$customerId}")->assertNoContent();
    }

    public function test_binding_anidado_direccion_acotado_al_cliente_padre(): void
    {
        $t = $this->seedTenant('a');

        $c1 = $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');
        $c2 = $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');

        $addr1 = $this->asUser($t->operator)->postJson("/api/v1/customers/{$c1}/addresses", [
            'label' => 'Casa', 'address_line' => 'Calle 1',
        ])->assertCreated()->json('data.id');

        // Acceder a la dirección de c1 COLGADA de c2 => 404 (scopeBindings), sin tocar nada.
        $this->asUser($t->operator)->putJson("/api/v1/customers/{$c2}/addresses/{$addr1}", ['label' => 'Fuga'])
            ->assertNotFound();
        $this->asUser($t->admin)->deleteJson("/api/v1/customers/{$c2}/addresses/{$addr1}")
            ->assertNotFound();

        // La dirección permanece intacta.
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addr1, 'customer_id' => $c1, 'label' => 'Casa',
        ]);

        // Otro negocio no puede listar/actualizar/eliminar la dirección de c1 (cliente 404).
        $b = $this->seedTenant('b');
        $this->asUser($b->operator)->getJson("/api/v1/customers/{$c1}/addresses")->assertNotFound();
        $this->asUser($b->operator)->putJson("/api/v1/customers/{$c1}/addresses/{$addr1}", ['label' => 'X'])
            ->assertNotFound();
        $this->asUser($b->admin)->deleteJson("/api/v1/customers/{$c1}/addresses/{$addr1}")
            ->assertNotFound();

        $this->assertDatabaseHas('customer_addresses', ['id' => $addr1, 'label' => 'Casa']);
    }

    public function test_proteccion_generico_alta_y_singleton(): void
    {
        $t = $this->seedTenant('a');

        // document_type=generico se rechaza en validación (Rule::in publicValues).
        $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload([
            'document_type' => 'generico', 'document_number' => 'GEN-X',
        ]))->assertStatus(422)->assertJsonValidationErrors(['document_type']);

        // is_generic=true en el payload NO crea otro genérico (fuera de $fillable).
        $this->asUser($t->operator)->postJson('/api/v1/customers', $this->customerPayload([
            'is_generic' => true,
        ]))->assertCreated()->assertJsonPath('data.is_generic', false);

        // Sigue existiendo EXACTAMENTE un genérico por negocio.
        $this->assertSame(1, Customer::withoutGlobalScopes()
            ->where('business_id', $t->business->id)->where('is_generic', true)->count());
    }

    public function test_candado_documento_en_update_y_reuso_tras_baja(): void
    {
        $t = $this->seedTenant('a');

        $c1 = $this->asUser($t->operator)->postJson('/api/v1/customers',
            $this->customerPayload(['document_number' => 'LOCK-1']))->json('data.id');
        $c2 = $this->asUser($t->operator)->postJson('/api/v1/customers',
            $this->customerPayload(['document_number' => 'LOCK-2']))->json('data.id');

        // Update de c2 al documento ACTIVO de c1 => 422 (candado document_number_lock).
        $this->asUser($t->admin)->putJson("/api/v1/customers/{$c2}", ['document_number' => 'LOCK-1'])
            ->assertStatus(422)->assertJsonValidationErrors(['document_number']);

        // Baja lógica de c1 (sin CxC viva) libera el documento.
        $this->asUser($t->admin)->deleteJson("/api/v1/customers/{$c1}")->assertNoContent();

        // Ahora el documento es reusable por c2.
        $this->asUser($t->admin)->putJson("/api/v1/customers/{$c2}", ['document_number' => 'LOCK-1'])
            ->assertOk()->assertJsonPath('data.document_number', 'LOCK-1');
    }

    public function test_operador_no_puede_eliminar_direccion(): void
    {
        $t = $this->seedTenant('a');
        $customerId = $this->asUser($t->operator)
            ->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');

        $addrId = $this->asUser($t->operator)->postJson("/api/v1/customers/{$customerId}/addresses", [
            'label' => 'Casa', 'address_line' => 'Calle 1',
        ])->assertCreated()->json('data.id');

        // ROL-03 crea/actualiza direcciones, pero NO las elimina (borrado físico es ROL-02).
        $this->asUser($t->operator)->deleteJson("/api/v1/customers/{$customerId}/addresses/{$addrId}")
            ->assertStatus(403);
        $this->assertDatabaseHas('customer_addresses', ['id' => $addrId]);
    }

    public function test_direcciones_respuesta_paginada(): void
    {
        $t = $this->seedTenant('a');
        $customerId = $this->asUser($t->operator)
            ->postJson('/api/v1/customers', $this->customerPayload())->json('data.id');

        for ($i = 0; $i < 3; $i++) {
            $this->asUser($t->operator)->postJson("/api/v1/customers/{$customerId}/addresses", [
                'label' => 'L'.$i, 'address_line' => 'Calle '.$i,
            ])->assertCreated();
        }

        // Envelope paginado completo {data, links, meta} con per_page respetado.
        $this->asUser($t->operator)->getJson("/api/v1/customers/{$customerId}/addresses?per_page=2")
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'links'  => ['first', 'last', 'prev', 'next'],
                'meta'   => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');

        // Default documentado = 15 cuando no se envía per_page.
        $this->asUser($t->operator)->getJson("/api/v1/customers/{$customerId}/addresses")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonCount(3, 'data');
    }
}
