<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\Business;
use App\Models\RegistrationRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\MysqlTestCase;

/**
 * Alta pública canónica: POST /api/v1/auth/register.
 *
 * Verifica atomicidad, idempotencia, aprovisionamiento del Observer, propietario + owner_user_id + ROL-01
 * bajo el team correcto, ausencia de autenticación/tokens, login posterior + /me, slug y errores de contrato.
 *
 * Concurrencia real (dos procesos con barreras) NO es demostrable en esta copia: Windows no provee pcntl y
 * MysqlTestCase envuelve cada prueba en una transacción revertida, por lo que una segunda conexión no ve los
 * fixtures no confirmados del test y PHP es monohilo. Se verifica el ÁRBITRO real (índice UNIQUE de uuid) y
 * sus resultados (reutilización/conflicto del ganador, rollback integral del perdedor) de forma determinista;
 * la colisión de slug SÍ se ejerce de verdad porque dos negocios homónimos chocan en el mismo conexión/tx.
 */
final class RegisterHttpTest extends MysqlTestCase
{
    private static int $seq = 0;

    private const PASSWORD = 'Str0ng-P@ssw0rd-9xQ';

    protected function setUp(): void
    {
        parent::setUp();

        // El catálogo global de permisos debe existir (ROL-SYS global + permisos). Idempotente.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(RolesAndPermissionsSeeder::class)->run();

        // Seam de prueba limpio: evita la llamada HTTP a HaveIBeenPwned de Password::uncompromised(),
        // conservando min(12)+letras+números+símbolos (la política efectiva que sí es determinista).
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier {
            public function verify($data): bool
            {
                return true;
            }
        });
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        $base = [
            'business' => [
                'name'     => 'Mi Negocio '.(++self::$seq),
                'timezone' => 'America/Managua',
            ],
            'owner' => [
                'first_name'            => 'Pablo',
                'last_name'             => 'Guerrero',
                'email'                 => 'pablo'.self::$seq.'@example.com',
                'password'              => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }

    private function register(array $payload, ?string $key = null, array $headers = [])
    {
        $headers = array_merge(['Idempotency-Key' => $key ?? $this->key()], $headers);

        return $this->postJson('/api/v1/auth/register', $payload, $headers);
    }

    /** Seedea un negocio+propietario autenticables (para el caso de visitante autenticado). */
    private function seedAuthenticatedOwner(): User
    {
        $business = Business::query()->create([
            'name' => 'Existente '.(++self::$seq), 'slug' => 'existente-'.self::$seq,
            'plan' => 'basic', 'status' => 'trial', 'timezone' => 'America/Managua',
        ]);
        $user = new User([
            'name' => 'Dueño', 'email' => 'dueno'.self::$seq.'@example.com',
            'password' => Hash::make(self::PASSWORD), 'is_active' => true, 'branch_id' => null,
        ]);
        $user->business_id = $business->id;
        $user->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        $user->assignRole(RoleName::Owner->value);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $user;
    }

    private function actingAsWeb(User $user): void
    {
        $fresh = User::query()->whereKey($user->getKey())->firstOrFail();
        if (! $fresh instanceof AuthenticatableContract) {
            throw new \RuntimeException('no auth');
        }
        $this->actingAs($fresh, 'web');
    }

    // ============================= Éxito y forma =============================

    public function test_registro_exitoso_201_forma_exacta_sin_datos_internos(): void
    {
        $p = $this->payload(['business' => ['name' => 'Mi Negocio'], 'owner' => ['email' => 'pablo@example.com']]);

        $this->register($p)
            ->assertCreated()
            ->assertExactJson(['data' => ['business_slug' => 'mi-negocio', 'owner_email' => 'pablo@example.com']]);
    }

    public function test_defaults_basic_trial_y_aprovisionamiento_del_observer(): void
    {
        $slug = $this->register($this->payload())->assertCreated()->json('data.business_slug');

        $business = Business::query()->where('slug', $slug)->firstOrFail();
        $this->assertSame('basic', $business->plan);
        $this->assertSame('trial', $business->status->value);

        // Aprovisionamiento canónico del Observer (sin alta adicional del visitante).
        $this->assertDatabaseHas('customers', ['business_id' => $business->id, 'is_generic' => 1]);
        $this->assertSame(6, DB::table('anomaly_rules')->where('business_id', $business->id)->count());
        $this->assertGreaterThan(0, DB::table('document_sequences')->where('business_id', $business->id)->count());
    }

    public function test_propietario_activo_branch_null_y_owner_user_id_coherente(): void
    {
        $email = 'owner.link@example.com';
        $slug = $this->register($this->payload(['owner' => ['email' => $email]]))->assertCreated()->json('data.business_slug');

        $business = Business::query()->where('slug', $slug)->firstOrFail();
        $owner = User::query()->where('business_id', $business->id)->where('email', $email)->firstOrFail();

        $this->assertTrue((bool) $owner->is_active);
        $this->assertNull($owner->branch_id);
        $this->assertSame($business->id, (int) $owner->business_id);
        $this->assertSame($owner->id, (int) $business->owner_user_id);
    }

    public function test_propietario_tiene_exactamente_rol01_bajo_su_team(): void
    {
        $slug = $this->register($this->payload())->assertCreated()->json('data.business_slug');
        $business = Business::query()->where('slug', $slug)->firstOrFail();
        $owner = User::query()->where('business_id', $business->id)->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $fresh = User::query()->whereKey($owner->id)->firstOrFail();

        $roles = $fresh->getRoleNames();
        $this->assertCount(1, $roles);
        $this->assertSame(RoleName::Owner->value, $roles->first());
        $this->assertTrue($fresh->hasRole(RoleName::Owner->value, 'web'));

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    public function test_registro_no_autentica_ni_emite_token_y_me_requiere_login(): void
    {
        $resp = $this->register($this->payload())->assertCreated();
        $this->assertArrayNotHasKey('token', (array) $resp->json('data'));

        // Sin login, /me es inaccesible.
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_login_posterior_con_slug_devuelto_y_me_coherente(): void
    {
        $email = 'login.me@example.com';
        $p     = $this->payload(['business' => ['name' => 'Negocio Login'], 'owner' => ['email' => $email]]);
        $slug  = $this->register($p)->assertCreated()->json('data.business_slug');

        // Login canónico del SPA: petición STATEFUL (Referer de dominio permitido) para que arranque la sesión.
        // CSRF se omite automáticamente bajo entorno de pruebas; no se desactiva middleware.
        $this->withHeader('Referer', 'http://localhost');

        $this->postJson('/api/v1/auth/login', [
            'business_slug' => $slug, 'email' => $email, 'password' => self::PASSWORD,
        ])->assertOk();

        // /me confirma el negocio correcto, ROL-01 y capacidades efectivas no vacías (MeResource no expone slug).
        $me = $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.role', RoleName::Owner->value)
            ->assertJsonPath('data.business.name', 'Negocio Login')
            ->assertJsonPath('data.business.status', 'trial');

        $this->assertNotEmpty($me->json('data.capabilities'));
    }

    public function test_mismo_email_en_dos_negocios_distintos(): void
    {
        $email = 'compartido@example.com';
        $slugA = $this->register($this->payload(['business' => ['name' => 'Negocio Uno'], 'owner' => ['email' => $email]]))
            ->assertCreated()->json('data.business_slug');
        $slugB = $this->register($this->payload(['business' => ['name' => 'Negocio Dos'], 'owner' => ['email' => $email]]))
            ->assertCreated()->json('data.business_slug');

        $this->assertNotSame($slugA, $slugB);
        $this->assertSame(2, User::query()->where('email', $email)->count());
    }

    // ============================= Normalización y hashing =============================

    public function test_normaliza_nombres_email_y_preserva_espacios_de_password(): void
    {
        $spaced = '  '.self::PASSWORD.'  '; // espacios deben PRESERVARSE
        $email  = 'MixedCase@Example.com ';

        $slug = $this->register($this->payload([
            'owner' => [
                'first_name' => '  Juan   Carlos ',
                'last_name'  => ' De   la  Rosa ',
                'email'      => $email,
                'password'   => $spaced,
                'password_confirmation' => $spaced,
            ],
        ]))->assertCreated()->assertJsonPath('data.owner_email', 'mixedcase@example.com')->json('data.business_slug');

        $business = Business::query()->where('slug', $slug)->firstOrFail();
        $owner = User::query()->where('business_id', $business->id)->firstOrFail();

        // Nombre combinado con espacios internos colapsados.
        $this->assertSame('Juan Carlos De la Rosa', $owner->name);
        // La contraseña con espacios se conserva EXACTA: verifica contra el valor espaciado, no el recortado.
        $this->assertTrue(Hash::check($spaced, $owner->password));
        $this->assertFalse(Hash::check(self::PASSWORD, $owner->password));
    }

    public function test_hash_verificable_sin_doble_hash(): void
    {
        $slug = $this->register($this->payload())->assertCreated()->json('data.business_slug');
        $owner = User::query()->where('business_id', Business::query()->where('slug', $slug)->value('id'))->firstOrFail();

        $this->assertNotSame(self::PASSWORD, $owner->password);           // no se guarda en claro
        $this->assertTrue(Hash::check(self::PASSWORD, $owner->password)); // un solo hash (doble hash fallaría)
    }

    // ============================= Validación =============================

    public function test_password_debil_confirmacion_timezone_y_nombre_combinado(): void
    {
        $this->register($this->payload(['owner' => ['password' => 'corta', 'password_confirmation' => 'corta']]))
            ->assertStatus(422)->assertJsonValidationErrors(['owner.password']);

        $this->register($this->payload(['owner' => ['password_confirmation' => 'otra-cosa-distinta']]))
            ->assertStatus(422)->assertJsonValidationErrors(['owner.password']);

        $this->register($this->payload(['business' => ['timezone' => 'Marte/Olimpo']]))
            ->assertStatus(422)->assertJsonValidationErrors(['business.timezone']);

        $this->register($this->payload(['owner' => ['first_name' => str_repeat('a', 140), 'last_name' => str_repeat('b', 40)]]))
            ->assertStatus(422)->assertJsonValidationErrors(['owner.first_name']);
    }

    public function test_tipos_incorrectos_no_producen_500(): void
    {
        $this->register(['business' => 'no-es-objeto', 'owner' => $this->payload()['owner']])
            ->assertStatus(422)->assertJsonValidationErrors(['business']);
    }

    public function test_claves_adicionales_privilegiadas_anidadas_rechazadas(): void
    {
        $p = $this->payload();
        $p['business_id'] = 0;
        $p['status'] = '';
        $p['business']['plan'] = null;
        $p['business']['slug'] = 'hackeado';
        $p['owner']['role'] = 'ROL-01';
        $p['owner']['roles'] = [];
        $p['owner']['payment'] = ['card_number' => '4111'];

        $this->register($p)->assertStatus(422)->assertJsonValidationErrors([
            'business_id', 'status', 'business.plan', 'business.slug', 'owner.role', 'owner.roles', 'owner.payment',
        ]);

        $this->assertSame(0, Business::query()->where('name', $p['business']['name'])->count());
    }

    public function test_header_idempotencia_ausente_invalido_y_no_sustituible_por_body(): void
    {
        // Ausente.
        $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);

        // Inválido.
        $this->register($this->payload(), 'no-es-uuid')
            ->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);

        // En el body no sustituye al header: es clave desconocida.
        $p = $this->payload();
        $p['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/api/v1/auth/register', $p) // sin header
            ->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);
    }

    // ============================= Idempotencia =============================

    public function test_replay_misma_clave_mismo_payload_orden_y_normalizacion_equivalentes(): void
    {
        $key = $this->key();
        $p = $this->payload(['business' => ['name' => 'Negocio Idem'], 'owner' => ['email' => 'idem@example.com']]);

        $first = $this->register($p, $key)->assertCreated()->json('data');

        // Mismo payload pero con orden distinto de claves y normalización equivalente (email en mayúsculas,
        // espacios de más en el nombre): mismo resultado, sin nuevas filas de dominio.
        $reordered = [
            'owner' => [
                'password_confirmation' => self::PASSWORD,
                'email'                 => 'IDEM@EXAMPLE.COM',
                'last_name'             => 'Guerrero',
                'password'              => self::PASSWORD,
                'first_name'            => 'Pablo',
            ],
            'business' => ['timezone' => 'America/Managua', 'name' => 'Negocio Idem'],
        ];

        $second = $this->register($reordered, $key)->assertCreated()->json('data');

        $this->assertSame($first, $second);
        $this->assertSame(1, Business::query()->where('slug', $first['business_slug'])->count());
        $this->assertSame(1, RegistrationRequest::query()->where('uuid', $key)->count());
        $this->assertSame(1, User::query()->where('email', 'idem@example.com')->count());
    }

    public function test_conflicto_misma_clave_payload_distinto_incluida_password(): void
    {
        $key = $this->key();
        $this->register($this->payload(['business' => ['name' => 'Conflicto A'], 'owner' => ['email' => 'c@example.com']]), $key)
            ->assertCreated();

        // Distinto nombre de negocio.
        $this->register($this->payload(['business' => ['name' => 'Conflicto B'], 'owner' => ['email' => 'c@example.com']]), $key)
            ->assertStatus(409)->assertJsonPath('code', 'REGISTRATION_IDEMPOTENCY_CONFLICT');

        // Misma clave, SOLO cambia la contraseña → también cambia el fingerprint → 409.
        $other = 'Different-P@ss-2xZ-9';
        $this->register($this->payload(['business' => ['name' => 'Conflicto A'], 'owner' => [
            'email' => 'c@example.com', 'password' => $other, 'password_confirmation' => $other,
        ]]), $key)->assertStatus(409)->assertJsonPath('code', 'REGISTRATION_IDEMPOTENCY_CONFLICT');
    }

    public function test_replay_conserva_resultado_original_tras_mutar_datos(): void
    {
        $key = $this->key();
        $p = $this->payload(['business' => ['name' => 'Negocio Mutable'], 'owner' => ['email' => 'mut@example.com']]);
        $first = $this->register($p, $key)->assertCreated()->json('data');

        // Muta datos del negocio y del propietario DESPUÉS del alta.
        $business = Business::query()->where('slug', $first['business_slug'])->firstOrFail();
        $business->forceFill(['slug' => 'slug-cambiado-'.self::$seq])->save();
        User::query()->where('business_id', $business->id)->update(['email' => 'cambiado@example.com']);

        // El replay devuelve el resultado ORIGINAL persistido (no los valores mutados).
        $this->register($p, $key)->assertCreated()->assertExactJson(['data' => $first]);
    }

    // ============================= Slug =============================

    public function test_slug_vacio_usa_base_segura(): void
    {
        // Símbolos que Str::slug reduce a cadena vacía (sin '@'/'&', que se transliteran a 'at'/'and').
        $slug = $this->register($this->payload(['business' => ['name' => '***###!!!']]))->assertCreated()->json('data.business_slug');
        $this->assertStringStartsWith('negocio', $slug);
    }

    public function test_nombres_iguales_generan_slugs_distintos(): void
    {
        $slugA = $this->register($this->payload(['business' => ['name' => 'Café Central']]))->assertCreated()->json('data.business_slug');
        $slugB = $this->register($this->payload(['business' => ['name' => 'Café Central']]))->assertCreated()->json('data.business_slug');

        $this->assertSame('cafe-central', $slugA);
        $this->assertNotSame($slugA, $slugB);
        $this->assertStringStartsWith('cafe-central-', $slugB);
    }

    public function test_slug_respeta_tope_de_longitud_con_sufijo(): void
    {
        // Nombre en el límite válido (150). El slug base y, ante colisión, el sufijado, nunca exceden 160.
        $name = str_repeat('a', 150);
        $slugA = $this->register($this->payload(['business' => ['name' => $name]]))->assertCreated()->json('data.business_slug');
        $slugB = $this->register($this->payload(['business' => ['name' => $name]]))->assertCreated()->json('data.business_slug');

        $this->assertLessThanOrEqual(160, strlen($slugA));
        $this->assertLessThanOrEqual(160, strlen($slugB)); // base recortada + sufijo, reservando espacio.
        $this->assertNotSame($slugA, $slugB);
    }

    public function test_agotamiento_de_reintentos_de_slug_da_409(): void
    {
        config(['gintly.registration.slug_retries' => 0]);

        $this->register($this->payload(['business' => ['name' => 'Único Nombre']]))->assertCreated();
        // Segundo homónimo con 0 reintentos: la colisión real del índice agota y produce 409 específico.
        $this->register($this->payload(['business' => ['name' => 'Único Nombre']]))
            ->assertStatus(409)->assertJsonPath('code', 'BUSINESS_SLUG_CONFLICT');
    }

    // ============================= Atomicidad / rollback =============================

    public function test_rollback_integral_ante_fallo_en_la_insercion_de_idempotencia(): void
    {
        // Colaborador de prueba: fuerza un fallo en el ÚLTIMO paso (inserción de idempotencia), dentro de la tx.
        RegistrationRequest::creating(function (): void {
            throw new \RuntimeException('fallo inducido de prueba');
        });

        $p = $this->payload(['business' => ['name' => 'Rollback Negocio'], 'owner' => ['email' => 'rb@example.com']]);
        $this->register($p)->assertStatus(500);

        // NADA se persiste: ni negocio, ni propietario, ni cliente genérico del Observer, ni idempotencia.
        $this->assertSame(0, Business::query()->where('name', 'Rollback Negocio')->count());
        $this->assertSame(0, User::query()->where('email', 'rb@example.com')->count());
        $this->assertSame(0, RegistrationRequest::query()->count());

        // El team de permisos se restauró (null) pese al fallo.
        $this->assertNull(app(PermissionRegistrar::class)->getPermissionsTeamId());

        RegistrationRequest::flushEventListeners();
    }

    public function test_tras_un_fallo_se_puede_reintentar_con_exito(): void
    {
        RegistrationRequest::creating(function (): void {
            throw new \RuntimeException('fallo inducido');
        });
        $key = $this->key();
        $this->register($this->payload(['owner' => ['email' => 'retry@example.com']]), $key)->assertStatus(500);
        RegistrationRequest::flushEventListeners();

        // La clave no quedó consumida ni hay filas parciales: un nuevo intento procede.
        $this->assertSame(0, RegistrationRequest::query()->where('uuid', $key)->count());
        $this->register($this->payload(['owner' => ['email' => 'retry@example.com']]), $key)->assertCreated();
    }

    public function test_team_restaurado_tras_registro_exitoso(): void
    {
        $this->register($this->payload())->assertCreated();
        $this->assertNull(app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    // ============================= Visitante autenticado / rate limit =============================

    public function test_visitante_autenticado_recibe_403_y_no_crea_nada(): void
    {
        $owner = $this->seedAuthenticatedOwner();
        $this->actingAsWeb($owner);

        $p = $this->payload(['business' => ['name' => 'No Deberia Crearse'], 'owner' => ['email' => 'no@example.com']]);
        $this->register($p)->assertStatus(403);

        $this->assertSame(0, Business::query()->where('name', 'No Deberia Crearse')->count());
    }

    public function test_rate_limit_429(): void
    {
        config(['gintly.registration.max_per_minute' => 5]);

        // 5 solicitudes (inválidas por header ausente) consumen el cupo antes de validar; la 6ª → 429.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', $this->payload(['owner' => ['email' => 'rl@example.com']]))
                ->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/register', $this->payload(['owner' => ['email' => 'rl@example.com']]))
            ->assertStatus(429);
    }

    // ============================= Lock de idempotencia (GET_LOCK) =============================

    public function test_team_restaurado_desde_un_valor_no_nulo(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(777777); // team previo NO nulo

        try {
            $result = app(\App\Services\Auth\RegistrationService::class)->register(
                [
                    'business' => ['name' => $this->uniqueName('TeamPrev'), 'timezone' => 'America/Managua'],
                    'owner'    => ['first_name' => 'Pablo', 'last_name' => 'Guerrero', 'email' => 'teamprev@example.com', 'password' => self::PASSWORD],
                ],
                (string) Str::uuid()
            );

            $this->assertArrayHasKey('business_slug', $result);
            // El team previo NO nulo se restaura exactamente (no se deja en null ni en el del negocio nuevo).
            $this->assertSame(777777, $registrar->getPermissionsTeamId());
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    public function test_lock_timeout_devuelve_500_sanitizado_sin_crear_nada(): void
    {
        config(['gintly.registration.lock_timeout_seconds' => 1]); // espera finita y breve

        $key  = (string) Str::uuid();
        $name = 'gintly_reg_'.$key;

        // Segunda conexión (sesión MySQL distinta) RETIENE el lock → la del registro no podrá adquirirlo.
        config(['database.connections.mysql_lock' => config('database.connections.mysql')]);
        $held = DB::connection('mysql_lock')->select('SELECT GET_LOCK(?, 0) AS l', [$name]);
        $this->assertSame(1, (int) $held[0]->l);

        try {
            $resp = $this->register(
                $this->payload(['business' => ['name' => 'Lock Timeout'], 'owner' => ['email' => 'lt@example.com']]),
                $key
            );

            $resp->assertStatus(500);

            // 500 SANITIZADO: tiene message, pero no filtra SQL, nombre del lock, fingerprint ni stack.
            $raw = json_encode($resp->json());
            $this->assertArrayHasKey('message', (array) $resp->json());
            $this->assertStringNotContainsString('GET_LOCK', (string) $raw);
            $this->assertStringNotContainsString('gintly_reg_', (string) $raw);
            $this->assertStringNotContainsString('SELECT', (string) $raw);

            // No se creó ningún negocio (el fallo ocurre antes de cualquier escritura).
            $this->assertSame(0, Business::query()->where('name', 'like', '%Lock Timeout%')->count());
        } finally {
            DB::connection('mysql_lock')->select('SELECT RELEASE_LOCK(?)', [$name]);
            DB::purge('mysql_lock');
        }
    }

    public function test_lock_liberado_tras_exito_y_tras_conflicto(): void
    {
        $key  = (string) Str::uuid();
        $name = 'gintly_reg_'.$key;

        $this->register($this->payload(['business' => ['name' => 'Lock Release'], 'owner' => ['email' => 'lr@example.com']]), $key)
            ->assertCreated();
        // Tras el éxito, el finally liberó el lock.
        $this->assertSame(1, (int) DB::select('SELECT IS_FREE_LOCK(?) AS f', [$name])[0]->f);

        // Replay con la MISMA clave y payload DISTINTO → 409; el lock también queda libre después.
        $this->register($this->payload(['business' => ['name' => 'Lock Release B'], 'owner' => ['email' => 'lr@example.com']]), $key)
            ->assertStatus(409);
        $this->assertSame(1, (int) DB::select('SELECT IS_FREE_LOCK(?) AS f', [$name])[0]->f);
    }

    private function uniqueName(string $prefix): string
    {
        return $prefix.' '.(++self::$seq);
    }
}
