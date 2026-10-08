<?php

declare(strict_types=1);

namespace Tests\Unit\Mod01;

use App\Enums\RoleName;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

final class DemoOwnerLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These tests must never create schema or fixtures in the application's database.
        $this->assertSame('testing', $this->app->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME));

        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('businesses')->insert([
            ['id' => 1, 'slug' => 'gintly-demo', 'owner_user_id' => null,
                'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00'],
            ['id' => 2, 'slug' => 'another-business', 'owner_user_id' => 99,
                'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00'],
        ]);
    }

    private function owner(): User
    {
        // Only the role lookup is a test double. The guarded UPDATE uses a real SQLite connection.
        $user = new class extends User {
            public bool $demoRole = true;

            public function hasRole($roles, ?string $guard = null): bool
            {
                return $this->demoRole && $roles === RoleName::Owner->value;
            }
        };
        $user->forceFill(['id' => 2, 'business_id' => 1, 'email' => 'propietario@gintly.test', 'is_active' => true]);
        $user->exists = true;

        return $user;
    }

    private function link(Business $business, User $owner): void
    {
        // Exercise only this repair, never the full demo seeder or its other fixtures.
        (new ReflectionMethod(UserSeeder::class, 'linkDemoOwner'))->invoke(new UserSeeder(), $business, $owner);
    }

    public function test_links_the_missing_demo_owner_only(): void
    {
        $this->link(Business::findOrFail(1), $this->owner());

        $this->assertSame(2, (int) DB::table('businesses')->where('id', 1)->value('owner_user_id'));
        $this->assertSame(99, (int) DB::table('businesses')->where('id', 2)->value('owner_user_id'));
    }

    public function test_repeated_link_does_not_modify_the_record(): void
    {
        $this->link(Business::findOrFail(1), $this->owner());
        $before = (array) DB::table('businesses')->where('id', 1)->first();

        $this->link(Business::findOrFail(1), $this->owner());

        $this->assertSame($before, (array) DB::table('businesses')->where('id', 1)->first());
    }

    public function test_preserves_an_existing_different_owner(): void
    {
        DB::table('businesses')->where('id', 1)->update(['owner_user_id' => 33]);
        $before = (array) DB::table('businesses')->where('id', 1)->first();

        $this->link(Business::findOrFail(1), $this->owner());

        $this->assertSame($before, (array) DB::table('businesses')->where('id', 1)->first());
    }

    public static function invalidContexts(): array
    {
        return [
            'another tenant' => ['business_id', 2],
            'another user' => ['email', 'another@gintly.test'],
            'inactive' => ['is_active', false],
            'not ROL-01' => ['demoRole', false],
            'not the demo business' => ['slug', 'another-business'],
        ];
    }

    #[DataProvider('invalidContexts')]
    public function test_rejects_an_incoherent_demo_context(string $field, mixed $value): void
    {
        $business = Business::findOrFail(1);
        $owner = $this->owner();
        if ($field === 'slug') {
            $business->slug = $value;
        } elseif ($field === 'demoRole') {
            $owner->demoRole = $value;
        } else {
            $owner->setAttribute($field, $value);
        }

        try {
            $this->link($business, $owner);
            $this->fail('An incoherent demo context must not be linked.');
        } catch (\LogicException) {
            $this->assertNull(DB::table('businesses')->where('id', 1)->value('owner_user_id'));
        }
    }

    public function test_public_migration_url_is_not_routable(): void
    {
        $this->get('/run-migrations')->assertNotFound();
    }
}
