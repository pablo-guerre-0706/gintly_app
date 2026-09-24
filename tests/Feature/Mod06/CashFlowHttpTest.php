<?php

declare(strict_types=1);

namespace Tests\Feature\Mod06;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-06 - Pruebas HTTP end-to-end contra MySQL (datos controlados, revertidos).
 *
 * Prueba las restricciones específicas del motor que SQLite no reproduce:
 * columnas generadas open_register_lock / open_user_lock (doble apertura),
 * columna generada difference (descuadre) y CHECKs de cash_movements.
 */
final class CashFlowHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    private function asUser(User $u): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        // Recarga desde MySQL para el guard web.
        $authenticatedUser = User::query()->whereKey($u->getKey())->firstOrFail();

        // instanceof estrecha el tipo al contrato exacto que exige actingAs()
        // (Illuminate\Contracts\Auth\Authenticatable), en vez de confiar en @var.
        if (! $authenticatedUser instanceof AuthenticatableContract) {
            throw new \RuntimeException('El usuario recargado no implementa Authenticatable.');
        }

        return $this->actingAs($authenticatedUser, 'web');
    }

    /**
     * Negocio sembrado NORMALMENTE (observer: roles + genérico) con DOS sucursales,
     * DOS operadores (uno por sucursal) y DOS cajas (una por sucursal). Alias de
     * compatibilidad: operator/branch/register = los de la sucursal 1.
     */
    private function seedTenant(string $slug): object
    {
        // La siembra debe ser independiente del contexto de autenticación previo.
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

        $branch  = $this->makeBranch($business, 'S1 '.$slug);
        $branch2 = $this->makeBranch($business, 'S2 '.$slug);

        $owner     = $this->makeUser($business, RoleName::Owner);            // sin sucursal (administrativo)
        $admin     = $this->makeUser($business, RoleName::Admin);           // sin sucursal (administrativo)
        $operator  = $this->makeUser($business, RoleName::Operator, $branch);
        $operator2 = $this->makeUser($business, RoleName::Operator, $branch2);

        $register  = $this->makeRegister($business, $branch,  'Caja '.$slug.'-1');
        $register2 = $this->makeRegister($business, $branch2, 'Caja '.$slug.'-2');

        return (object) compact(
            'business', 'owner', 'admin', 'operator', 'operator2',
            'branch', 'branch2', 'register', 'register2',
        );
    }

    private function makeBranch(Business $business, string $name): Branch
    {
        $branch = new Branch();
        $branch->forceFill([
            'business_id' => $business->id,
            'name'        => $name,
            'address'     => 'Dir. '.$name,
            'opened_at'   => now()->toDateString(),
            'is_active'   => true,
        ])->saveQuietly();

        return $branch;
    }

    private function makeUser(Business $business, RoleName $role, ?Branch $branch = null, bool $active = true): User
    {
        $user = new User([
            'name'      => $role->value.' '.(++self::$seq),
            'email'     => 'u'.self::$seq.'@test.local',
            'password'  => Hash::make('secret-Password-123'),
            'is_active' => $active,
        ]);
        $user->business_id = $business->id;
        $user->branch_id   = $branch?->id;
        $user->save();

        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $user->assignRole($role->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $user;
    }

    private function makeRegister(Business $business, Branch $branch, string $name): CashRegister
    {
        $register = new CashRegister();
        $register->forceFill([
            'business_id' => $business->id,
            'branch_id'   => $branch->id,
            'name'        => $name,
            'is_active'   => true,
        ])->save();

        return $register;
    }

    /** Abre una sesión por API y devuelve el id. */
    private function openSession(User $actor, int $registerId, string $opening = '100.00'): int
    {
        return $this->asUser($actor)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $registerId,
            'opening_amount'   => $opening,
        ])->assertCreated()->json('data.id');
    }

    private function movement(User $actor, int $sessionId, array $overrides): \Illuminate\Testing\TestResponse
    {
        return $this->asUser($actor)->postJson('/api/v1/cash-movements', array_merge([
            'cash_session_id' => $sessionId,
            'type'            => 'ingreso',
            'category'        => 'ajuste',
            'payment_method'  => 'efectivo',
            'amount'          => '10.00',
        ], $overrides));
    }

    /** Cierra una sesión por API. Por defecto cuadra 100.00 (apertura sin movimientos). */
    private function closeSession(User $actor, int $sessionId, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->asUser($actor)->postJson("/api/v1/cash-sessions/{$sessionId}/close", array_merge([
            'counted_amount'        => '100.00',
            'counted_denominations' => [['value' => '100.00', 'qty' => 1]],
        ], $overrides));
    }

    // -----------------------------------------------------------------------

    public function test_operador_abre_sesion_y_arqueo_ciego_oculta_esperado(): void
    {
        $t = $this->seedTenant('a');

        // Apertura (ROL-03). Antes fallaba con 403 por can('open') inexistente.
        $created = $this->asUser($t->operator)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $t->register->id,
            'opening_amount'   => '100.00',
        ]);
        $created->assertCreated()
            ->assertJsonPath('data.status', 'abierta')
            ->assertJsonPath('data.opening_amount', '100.00');

        $sessionId = $created->json('data.id');

        // Arqueo ciego: mientras 'abierta', expected_amount y difference NO se exponen.
        $this->asUser($t->operator)->getJson("/api/v1/cash-sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'abierta')
            ->assertJsonPath('data.expected_amount', null)
            ->assertJsonPath('data.difference', null);
    }

    public function test_cierre_cuadrado_persiste_desglose_y_calcula_esperado_solo_efectivo(): void
    {
        $t = $this->seedTenant('a');
        $sessionId = $this->openSession($t->operator, $t->register->id, '100.00');

        // +50 efectivo (ajuste), -30 efectivo (retiro), +999 transferencia (NO cuenta).
        $this->movement($t->operator, $sessionId, ['type' => 'ingreso', 'category' => 'ajuste', 'amount' => '50.00'])->assertCreated();
        $this->movement($t->operator, $sessionId, ['type' => 'egreso', 'category' => 'retiro', 'amount' => '30.00'])->assertCreated();
        $this->movement($t->operator, $sessionId, ['type' => 'ingreso', 'category' => 'ajuste', 'payment_method' => 'transferencia', 'amount' => '999.00'])->assertCreated();

        // Esperado en efectivo = 100 + 50 - 30 = 120.00 (la transferencia no afecta la gaveta).
        $close = $this->asUser($t->operator)->postJson("/api/v1/cash-sessions/{$sessionId}/close", [
            'counted_amount'        => '120.00',
            'counted_denominations' => [
                ['value' => '100.00', 'qty' => 1],
                ['value' => '20.00', 'qty' => 1],
            ],
            'closing_notes' => 'Cuadre exacto',
        ]);

        $close->assertOk()
            ->assertJsonPath('data.status', 'cerrada')
            ->assertJsonPath('data.expected_amount', '120.00')
            ->assertJsonPath('data.difference', '0.00');

        // El desglose SÍ se persiste (antes se leía la clave errónea y quedaba vacío).
        $this->assertDatabaseHas('cash_sessions', [
            'id'              => $sessionId,
            'status'          => 'cerrada',
            'counted_amount'  => '120.00',
            'expected_amount' => '120.00',
        ]);
        $stored = \App\Models\CashSession::withoutGlobalScopes()->findOrFail($sessionId);
        $this->assertNotEmpty($stored->counted_denominations, 'El desglose del arqueo debe persistir.');
        $this->assertSame(2, count($stored->counted_denominations));
    }

    public function test_cierre_descuadrado_persiste_evidencia_y_devuelve_422(): void
    {
        $t = $this->seedTenant('a');
        $sessionId = $this->openSession($t->operator, $t->register->id, '100.00');

        // Esperado = 100. Se declara 125 → descuadre de +25.
        $close = $this->asUser($t->operator)->postJson("/api/v1/cash-sessions/{$sessionId}/close", [
            'counted_amount'        => '125.00',
            'counted_denominations' => [
                ['value' => '100.00', 'qty' => 1],
                ['value' => '25.00', 'qty' => 1],
            ],
        ]);

        $close->assertStatus(422)
            ->assertJsonPath('error', 'UNRECONCILED_CASH_CLOSING')
            ->assertJsonPath('data.status', 'descuadrada')
            ->assertJsonPath('data.difference', '25.00');

        // La evidencia PERSISTE (commit antes del 422): sin rollback.
        $this->assertDatabaseHas('cash_sessions', [
            'id'     => $sessionId,
            'status' => 'descuadrada',
        ]);
    }

    public function test_desglose_no_iguala_contado_devuelve_422(): void
    {
        $t = $this->seedTenant('a');
        $sessionId = $this->openSession($t->operator, $t->register->id, '100.00');

        $this->asUser($t->operator)->postJson("/api/v1/cash-sessions/{$sessionId}/close", [
            'counted_amount'        => '120.00',
            'counted_denominations' => [
                ['value' => '100.00', 'qty' => 1],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['counted_denominations']);
    }

    public function test_doble_apertura_misma_caja_devuelve_409_register_busy(): void
    {
        $t = $this->seedTenant('a');
        $this->openSession($t->operator, $t->register->id);

        // Otra apertura de la MISMA caja (otro usuario) → open_register_lock (motor).
        $this->asUser($t->admin)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $t->register->id,
            'opening_amount'   => '0.00',
        ])->assertStatus(409)->assertJsonPath('error', 'CASH_REGISTER_BUSY');
    }

    public function test_doble_apertura_mismo_usuario_devuelve_409_user_busy(): void
    {
        $t = $this->seedTenant('a');
        $register2 = $this->makeRegister($t->business, $t->branch, 'Caja a-2');

        $this->openSession($t->operator, $t->register->id);

        // El MISMO cajero abre en OTRA caja → open_user_lock (motor).
        $this->asUser($t->operator)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $register2->id,
            'opening_amount'   => '0.00',
        ])->assertStatus(409)->assertJsonPath('error', 'CASH_USER_BUSY');
    }

    public function test_movimiento_sin_sesion_abierta_devuelve_409(): void
    {
        $t = $this->seedTenant('a');
        $sessionId = $this->openSession($t->operator, $t->register->id);

        // Cierra la sesión (cuadre exacto: sin movimientos, esperado = apertura 100).
        $this->asUser($t->operator)->postJson("/api/v1/cash-sessions/{$sessionId}/close", [
            'counted_amount'        => '100.00',
            'counted_denominations' => [['value' => '100.00', 'qty' => 1]],
        ])->assertOk();

        // Movimiento sobre sesión CERRADA → 409 NO_ACTIVE_CASH_SESSION.
        $this->movement($t->operator, $sessionId, ['category' => 'ajuste', 'type' => 'ingreso'])
            ->assertStatus(409)->assertJsonPath('error', 'NO_ACTIVE_CASH_SESSION');
    }

    public function test_type_incoherente_con_category_devuelve_422(): void
    {
        $t = $this->seedTenant('a');
        $sessionId = $this->openSession($t->operator, $t->register->id);

        // venta ⇒ ingreso; enviar egreso viola forcedType().
        $this->movement($t->operator, $sessionId, ['category' => 'venta', 'type' => 'egreso'])
            ->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_egreso_autorizado_exige_autorizante_admin(): void
    {
        $t = $this->seedTenant('a');
        $sessionId = $this->openSession($t->operator, $t->register->id);

        $base = [
            'category'       => 'egreso_autorizado',
            'type'           => 'egreso',
            'payment_method' => 'efectivo',
            'amount'         => '10.00',
        ];

        // Sin authorized_by → 422 (required_if).
        $this->movement($t->operator, $sessionId, $base)
            ->assertStatus(422)->assertJsonValidationErrors(['authorized_by']);

        // authorized_by = ROL-03 (no admin) → 422 (CashAuthorizationException).
        $this->movement($t->operator, $sessionId, $base + ['authorized_by' => $t->operator->id])
            ->assertStatus(422);

        // authorized_by = ROL-02 → 201.
        $this->movement($t->operator, $sessionId, $base + ['authorized_by' => $t->admin->id])
            ->assertCreated()->assertJsonPath('data.category', 'egreso_autorizado');
    }

    public function test_caja_crud_y_nombre_duplicado(): void
    {
        $t = $this->seedTenant('a');

        // ROL-03 NO puede crear cajas (create = ROL-02+).
        $this->asUser($t->operator)->postJson('/api/v1/cash-registers', [
            'branch_id' => $t->branch->id, 'name' => 'Caja X',
        ])->assertStatus(403);

        // ROL-02 crea.
        $this->asUser($t->admin)->postJson('/api/v1/cash-registers', [
            'branch_id' => $t->branch->id, 'name' => 'Caja X',
        ])->assertCreated();

        // Nombre duplicado en (business, branch) → 422.
        $this->asUser($t->admin)->postJson('/api/v1/cash-registers', [
            'branch_id' => $t->branch->id, 'name' => 'Caja X',
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_index_filtros_paginacion_y_movimientos_por_medio(): void
    {
        $t = $this->seedTenant('a');
        $sessionId = $this->openSession($t->operator, $t->register->id);

        $this->movement($t->operator, $sessionId, ['payment_method' => 'efectivo', 'category' => 'ajuste', 'type' => 'ingreso', 'amount' => '5.00'])->assertCreated();
        $this->movement($t->operator, $sessionId, ['payment_method' => 'transferencia', 'category' => 'ajuste', 'type' => 'ingreso', 'amount' => '7.00'])->assertCreated();

        // Sesiones: filtro status=abierta + envelope paginado.
        $this->asUser($t->operator)->getJson('/api/v1/cash-sessions?status=abierta&per_page=10')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total']])
            ->assertJsonPath('meta.per_page', 10);

        // Movimientos (viewAny = ROL-02+): filtro payment_method=transferencia (ahora cableado).
        $this->asUser($t->admin)->getJson("/api/v1/cash-movements?cash_session_id={$sessionId}&payment_method=transferencia")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.payment_method', 'transferencia');

        // ROL-03 NO puede listar el libro global de movimientos (viewAny = ROL-02+).
        $this->asUser($t->operator)->getJson('/api/v1/cash-movements')->assertStatus(403);
    }

    public function test_aislamiento_entre_negocios(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');

        $sessionA = $this->openSession($a->operator, $a->register->id);

        // B no ve la sesión de A (BusinessScope → 404 en el binding).
        $this->asUser($b->operator)->getJson("/api/v1/cash-sessions/{$sessionA}")->assertNotFound();
    }

    // =======================================================================
    // Microcierre: alcance por rol, propiedad (ROL-03) y aislamiento de sucursal.
    // =======================================================================

    public function test_rol03_index_solo_sus_sesiones_y_opened_by_no_amplia(): void
    {
        $t = $this->seedTenant('a');
        $s1 = $this->openSession($t->operator, $t->register->id);
        $s2 = $this->openSession($t->operator2, $t->register2->id);

        // ROL-03 (operator1) ve SOLO su sesión.
        $own = $this->asUser($t->operator)->getJson('/api/v1/cash-sessions')->assertOk();
        $ids = collect($own->json('data'))->pluck('id')->all();
        $this->assertContains($s1, $ids);
        $this->assertNotContains($s2, $ids);

        // opened_by de otro usuario NO amplía el alcance del ROL-03.
        $spoof = $this->asUser($t->operator)->getJson("/api/v1/cash-sessions?opened_by={$t->operator2->id}")->assertOk();
        $spoofIds = collect($spoof->json('data'))->pluck('id')->all();
        $this->assertSame([$s1], $spoofIds);

        // ROL-02 ve las sesiones de AMBOS operadores.
        $adminIds = collect($this->asUser($t->admin)->getJson('/api/v1/cash-sessions')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($s1, $adminIds);
        $this->assertContains($s2, $adminIds);

        // ROL-02 sí puede filtrar por opened_by.
        $filtered = collect($this->asUser($t->admin)->getJson("/api/v1/cash-sessions?opened_by={$t->operator->id}")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$s1], $filtered);
    }

    public function test_rol03_no_opera_sobre_sesion_ajena(): void
    {
        $t = $this->seedTenant('a');
        $this->openSession($t->operator, $t->register->id);
        $s2 = $this->openSession($t->operator2, $t->register2->id);

        // show ajeno → 403.
        $this->asUser($t->operator)->getJson("/api/v1/cash-sessions/{$s2}")->assertStatus(403);

        // movements ajeno → 403.
        $this->asUser($t->operator)->getJson("/api/v1/cash-sessions/{$s2}/movements")->assertStatus(403);

        // crear movimiento en sesión ajena → 403 y NO persiste.
        $this->movement($t->operator, $s2, ['category' => 'ajuste', 'type' => 'ingreso', 'amount' => '5.00'])
            ->assertStatus(403);
        $this->assertDatabaseMissing('cash_movements', [
            'cash_session_id' => $s2,
            'user_id'         => $t->operator->id,
        ]);

        // cerrar sesión ajena → 403 y sigue abierta.
        $this->asUser($t->operator)->postJson("/api/v1/cash-sessions/{$s2}/close", [
            'counted_amount'        => '100.00',
            'counted_denominations' => [['value' => '100.00', 'qty' => 1]],
        ])->assertStatus(403);
        $this->assertDatabaseHas('cash_sessions', ['id' => $s2, 'status' => 'abierta']);
    }

    public function test_rol03_no_abre_caja_de_otra_sucursal(): void
    {
        $t = $this->seedTenant('a');

        // operator1 (sucursal 1) intenta abrir la caja de la sucursal 2 → 403, sin sesión.
        $this->asUser($t->operator)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $t->register2->id,
            'opening_amount'   => '0.00',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('cash_sessions', [
            'cash_register_id' => $t->register2->id,
            'opened_by'        => $t->operator->id,
        ]);
    }

    public function test_operador_sin_sucursal_no_abre_caja(): void
    {
        $t = $this->seedTenant('a');
        $sinSucursal = $this->makeUser($t->business, RoleName::Operator, null);

        $this->asUser($sinSucursal)->postJson('/api/v1/cash-sessions', [
            'cash_register_id' => $t->register->id,
            'opening_amount'   => '0.00',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('cash_sessions', ['opened_by' => $sinSucursal->id]);
    }

    public function test_rol03_solo_lista_cajas_activas_de_su_sucursal(): void
    {
        $t = $this->seedTenant('a');

        // operator1 (sucursal 1) solo ve la caja de su sucursal.
        $ids = collect($this->asUser($t->operator)->getJson('/api/v1/cash-registers')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertContains($t->register->id, $ids);
        $this->assertNotContains($t->register2->id, $ids);

        // ROL-02 administra las cajas de ambas sucursales.
        $adminIds = collect($this->asUser($t->admin)->getJson('/api/v1/cash-registers')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertContains($t->register->id, $adminIds);
        $this->assertContains($t->register2->id, $adminIds);
    }

    public function test_arqueo_ciego_oculto_incluso_para_admin_en_sesion_abierta(): void
    {
        $t = $this->seedTenant('a');
        $s1 = $this->openSession($t->operator, $t->register->id);

        // Aun con visibilidad administrativa, el arqueo ciego se mantiene mientras 'abierta'.
        $this->asUser($t->admin)->getJson("/api/v1/cash-sessions/{$s1}")
            ->assertOk()
            ->assertJsonPath('data.status', 'abierta')
            ->assertJsonPath('data.expected_amount', null)
            ->assertJsonPath('data.difference', null);
    }

    public function test_otro_negocio_no_ve_sesiones_ni_por_index(): void
    {
        $a = $this->seedTenant('a');
        $b = $this->seedTenant('b');
        $sessionA = $this->openSession($a->operator, $a->register->id);

        // Index de B (ROL-02): no aparece la sesión de A.
        $ids = collect($this->asUser($b->admin)->getJson('/api/v1/cash-sessions')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertNotContains($sessionA, $ids);

        // Show cruzado → 404 (BusinessScope en el binding).
        $this->asUser($b->admin)->getJson("/api/v1/cash-sessions/{$sessionA}")->assertNotFound();
    }

    public function test_egreso_autorizado_valida_autorizante_activo_y_del_tenant(): void
    {
        $t = $this->seedTenant('a');
        $otro = $this->seedTenant('b');
        $adminInactivo = $this->makeUser($t->business, RoleName::Admin, null, active: false);

        $sessionId = $this->openSession($t->operator, $t->register->id);

        $base = [
            'category'       => 'egreso_autorizado',
            'type'           => 'egreso',
            'payment_method' => 'efectivo',
            'amount'         => '10.00',
        ];

        // Autorizante ROL-02 inactivo → 422 (CashAuthorizationException), sin persistir.
        $this->movement($t->operator, $sessionId, $base + ['authorized_by' => $adminInactivo->id])
            ->assertStatus(422);

        // Autorizante de OTRO negocio → 422 (validación tenantExists), sin persistir.
        $this->movement($t->operator, $sessionId, $base + ['authorized_by' => $otro->admin->id])
            ->assertStatus(422);

        $this->assertDatabaseMissing('cash_movements', [
            'cash_session_id' => $sessionId,
            'category'        => 'egreso_autorizado',
        ]);

        // Autorizante ROL-02 activo del mismo negocio → 201.
        $this->movement($t->operator, $sessionId, $base + ['authorized_by' => $t->admin->id])
            ->assertCreated()->assertJsonPath('data.category', 'egreso_autorizado');
    }

    // =======================================================================
    // Cierre ordinario vs. cierre administrativo por contingencia.
    // =======================================================================

    public function test_cierre_ordinario_propietario_motivo_opcional(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t->operator, $t->register->id);

        // El propietario cierra SU sesión sin closing_notes → 200.
        $this->closeSession($t->operator, $s)
            ->assertOk()
            ->assertJsonPath('data.status', 'cerrada')
            ->assertJsonPath('data.closed_by', $t->operator->id);
    }

    public function test_cierre_contingencia_rol02_con_motivo_closed_by_admin(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t->operator, $t->register->id);

        // ROL-02 (distinto del que abrió) cierra con motivo → 200; closed_by = admin.
        $this->closeSession($t->admin, $s, ['closing_notes' => 'Cierre por fin de turno del cajero'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cerrada')
            ->assertJsonPath('data.closed_by', $t->admin->id);

        $this->assertDatabaseHas('cash_sessions', [
            'id'        => $s,
            'status'    => 'cerrada',
            'closed_by' => $t->admin->id,
        ]);
    }

    public function test_cierre_contingencia_rol01_tambien_procede(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t->operator, $t->register->id);

        // ROL-01 (Owner) ejecuta el mismo cierre de contingencia.
        $this->closeSession($t->owner, $s, ['closing_notes' => 'Contingencia autorizada por gerencia'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cerrada')
            ->assertJsonPath('data.closed_by', $t->owner->id);
    }

    public function test_cierre_contingencia_sin_motivo_422_y_sesion_intacta(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t->operator, $t->register->id);

        // ROL-02 sin closing_notes → 422 (motivo obligatorio en contingencia).
        $this->closeSession($t->admin, $s)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['closing_notes']);

        // La sesión permanece ABIERTA, sin conteo, closed_at ni closed_by.
        $this->assertDatabaseHas('cash_sessions', [
            'id'             => $s,
            'status'         => 'abierta',
            'counted_amount' => null,
            'expected_amount' => null,
            'closed_at'      => null,
            'closed_by'      => null,
        ]);
    }

    public function test_rol03_no_cierra_sesion_ajena_ni_con_motivo(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t->operator, $t->register->id);

        // Otro ROL-03 (operator2) NUNCA cierra la sesión, aunque envíe motivo → 403.
        $this->closeSession($t->operator2, $s, ['closing_notes' => 'Intento de cierre por otro cajero'])
            ->assertStatus(403);

        // Sesión intacta.
        $this->assertDatabaseHas('cash_sessions', [
            'id'        => $s,
            'status'    => 'abierta',
            'closed_by' => null,
        ]);
    }

    public function test_cierre_contingencia_descuadrado_persiste_evidencia_y_anomalia(): void
    {
        $t = $this->seedTenant('a');
        $s = $this->openSession($t->operator, $t->register->id, '100.00'); // esperado 100

        // ROL-02 cierra en contingencia con descuadre (+25) y motivo.
        $close = $this->closeSession($t->admin, $s, [
            'counted_amount'        => '125.00',
            'counted_denominations' => [
                ['value' => '100.00', 'qty' => 1],
                ['value' => '25.00', 'qty' => 1],
            ],
            'closing_notes' => 'Descuadre detectado durante contingencia',
        ]);

        // 422 UNRECONCILED DESPUÉS del commit, con evidencia en el cuerpo.
        $close->assertStatus(422)
            ->assertJsonPath('error', 'UNRECONCILED_CASH_CLOSING')
            ->assertJsonPath('data.status', 'descuadrada')
            ->assertJsonPath('data.difference', '25.00')
            ->assertJsonPath('data.closed_by', $t->admin->id);

        // Persistencia: estado, closed_by (admin) y motivo.
        $this->assertDatabaseHas('cash_sessions', [
            'id'        => $s,
            'status'    => 'descuadrada',
            'closed_by' => $t->admin->id,
        ]);
        $fresh = \App\Models\CashSession::withoutGlobalScopes()->findOrFail($s);
        $this->assertSame('Descuadre detectado durante contingencia', $fresh->closing_notes);

        // Anomalía de descuadre persistida (origen = la sesión; source_type = tabla).
        $this->assertDatabaseHas('anomalies', [
            'source_type' => 'cash_sessions',
            'source_id'   => $s,
        ]);
    }
}
