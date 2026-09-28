<?php

declare(strict_types=1);

namespace Tests\Feature\Mod06;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use App\Services\Cash\CashService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * MOD-06 · Invariante de dominio: el cobro en efectivo de una venta se asienta SOLO en la sesión
 * PROPIA del operador. Se verifica a nivel de Service (CashService::registrarMovimientoVenta), de modo
 * que una llamada interna no pueda saltarse el FormRequest y usar la sesión de otro usuario.
 */
final class CashSaleSessionOwnershipTest extends MysqlTestCase
{
    private static int $seq = 0;

    private function makeUser(Business $b, ?Branch $branch): User
    {
        $u = new User(['name' => 'op'.(++self::$seq), 'email' => 'u'.self::$seq.'@t.local', 'password' => Hash::make('secret-Password-123'), 'is_active' => true, 'branch_id' => $branch?->id]);
        $u->business_id = $b->id;
        $u->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
        $u->assignRole(RoleName::Operator->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $u;
    }

    private function openSession(Business $b, CashRegister $register, User $opener): CashSession
    {
        $s = new CashSession();
        $s->forceFill([
            'business_id' => $b->id, 'cash_register_id' => $register->id, 'opened_by' => $opener->id,
            'status' => 'abierta', 'opening_amount' => '0.00', 'opened_at' => now(),
        ])->saveQuietly();

        return $s->refresh();
    }

    public function test_venta_efectivo_no_usa_la_sesion_de_otro_usuario(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(RolesAndPermissionsSeeder::class)->run();

        $business = Business::create([
            'name' => 'Neg', 'slug' => 'neg-own-'.(++self::$seq), 'plan' => 'basic',
            'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
        ]);
        $branch = new Branch();
        $branch->forceFill(['business_id' => $business->id, 'name' => 'S1', 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();

        $registerA = new CashRegister();
        $registerA->forceFill(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Caja A', 'is_active' => true])->save();
        $registerB = new CashRegister();
        $registerB->forceFill(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Caja B', 'is_active' => true])->save();

        $opA = $this->makeUser($business, $branch);
        $opB = $this->makeUser($business, $branch);
        $sessionB = $this->openSession($business, $registerB, $opB); // sesión de OTRO usuario.

        $threw = false;
        try {
            // opA intenta asentar un cobro en efectivo sobre la sesión de opB.
            app(CashService::class)->registrarMovimientoVenta($opA, $sessionB->id, '10.00', 999);
        } catch (AuthorizationException $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Debe rechazar operar la sesión de otro usuario.');
        // No se generó ningún movimiento en la sesión ajena.
        $this->assertSame(0, CashMovement::withoutGlobalScopes()->where('cash_session_id', $sessionB->id)->count());
    }

    public function test_venta_efectivo_en_sesion_propia_registra_movimiento(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(RolesAndPermissionsSeeder::class)->run();

        $business = Business::create([
            'name' => 'Neg', 'slug' => 'neg-own2-'.(++self::$seq), 'plan' => 'basic',
            'status' => 'active', 'tax_rate' => '0.1500', 'timezone' => 'America/Managua',
        ]);
        $branch = new Branch();
        $branch->forceFill(['business_id' => $business->id, 'name' => 'S1', 'address' => 'x', 'opened_at' => now()->toDateString(), 'is_active' => true])->saveQuietly();
        $register = new CashRegister();
        $register->forceFill(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Caja', 'is_active' => true])->save();

        $opA = $this->makeUser($business, $branch);
        $sessionA = $this->openSession($business, $register, $opA); // su PROPIA sesión.

        // Contexto autenticado equivalente al flujo HTTP real: BelongsToBusiness toma business_id de Auth.
        $this->actingAs($opA, 'web');
        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);

        // Control positivo: sobre su propia sesión sí se asienta el movimiento de venta.
        $movement = app(CashService::class)->registrarMovimientoVenta($opA, $sessionA->id, '10.00', $this->fakeSaleId($business, $branch, $opA));

        $this->assertSame($sessionA->id, $movement->cash_session_id);
        $this->assertSame(1, CashMovement::withoutGlobalScopes()->where('cash_session_id', $sessionA->id)->count());
    }

    /** Crea una venta mínima para satisfacer la FK cash_movements.sale_id del control positivo. */
    private function fakeSaleId(Business $b, Branch $branch, User $u): int
    {
        $customerId = (int) \Illuminate\Support\Facades\DB::table('customers')->insertGetId([
            'business_id' => $b->id, 'name' => 'Cliente '.(++self::$seq), 'document_type' => 'generico',
            'is_generic' => false, 'is_active' => true, 'credit_limit' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) \Illuminate\Support\Facades\DB::table('sales')->insertGetId([
            'business_id' => $b->id, 'branch_id' => $branch->id, 'customer_id' => $customerId,
            'user_id' => $u->id, 'code' => 'V-'.(++self::$seq), 'status' => 'confirmada',
            'subtotal' => '10.00', 'opened_at' => now(), 'confirmed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
