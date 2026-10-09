<?php

declare(strict_types=1);

namespace Tests\Unit\Frontend;

use App\Support\EnvironmentAwareVite;
use Illuminate\Foundation\Vite;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EnvironmentAwareViteTest extends TestCase
{
    private string $hotPointer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hotPointer = tempnam(sys_get_temp_dir(), 'gintly-vite-test-');
        file_put_contents($this->hotPointer, 'http://[::1]:5173');
        $this->app->make(Vite::class)->useHotFile($this->hotPointer);
    }

    protected function tearDown(): void
    {
        unlink($this->hotPointer);
        parent::tearDown();
    }

    public function test_local_development_keeps_its_existing_hot_server(): void
    {
        $this->app['env'] = 'local';
        $vite = $this->app->make(Vite::class);

        $this->assertInstanceOf(EnvironmentAwareVite::class, $vite);
        $this->assertTrue($vite->isRunningHot());
        $html = $vite(['resources/js/modules/registration/wizard.js'])->toHtml();
        $this->assertStringContainsString('http://[::1]:5173/@vite/client', $html);
        $this->assertStringContainsString('http://[::1]:5173/resources/js/modules/registration/wizard.js', $html);
        $this->assertFileExists($this->hotPointer);
    }

    public static function deployedEnvironments(): array
    {
        return [['production'], ['staging'], ['testing']];
    }

    #[DataProvider('deployedEnvironments')]
    public function test_deployed_registration_uses_the_manifest_even_with_a_stale_hot_pointer(string $environment): void
    {
        $this->app['env'] = $environment;
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        $html = view('auth.register')->render();

        $this->assertFalse($this->app->make(Vite::class)->isRunningHot());
        $this->assertStringContainsString('/build/'.$manifest['resources/js/modules/registration/wizard.js']['file'], $html);
        $this->assertStringContainsString('/build/'.$manifest['resources/css/app.css']['file'], $html);
        $this->assertStringNotContainsString('@vite/client', $html);
        $this->assertStringNotContainsString('[::1]:5173', $html);
        $this->assertStringContainsString('data-register-form', $html);
        $this->assertFileExists($this->hotPointer);
    }

    public function test_local_without_a_hot_pointer_uses_the_build(): void
    {
        $this->app['env'] = 'local';
        $this->app->make(Vite::class)->useHotFile($this->hotPointer.'-absent');

        $this->assertFalse($this->app->make(Vite::class)->isRunningHot());
        $this->assertStringContainsString('/build/', view('auth.register')->render());
    }
}
