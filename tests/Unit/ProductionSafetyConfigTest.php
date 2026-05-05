<?php

namespace Tests\Unit;

use App\Http\Kernel;
use Barryvdh\Debugbar\Middleware\InjectDebugbar;
use ReflectionProperty;
use Tests\TestCase;

class ProductionSafetyConfigTest extends TestCase
{
    public function test_debugbar_is_disabled_by_default_outside_local(): void
    {
        $this->assertFalse((bool) config('debugbar.enabled'));
    }

    public function test_debugbar_is_not_auto_discovered(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertContains(
            'barryvdh/laravel-debugbar',
            $composer['extra']['laravel']['dont-discover'] ?? []
        );
    }

    public function test_session_fallback_driver_is_file(): void
    {
        $sessionConfig = (string) file_get_contents(config_path('session.php'));

        $this->assertStringContainsString("'driver' => env('SESSION_DRIVER', 'file')", $sessionConfig);
    }

    public function test_session_lottery_is_configurable_by_env(): void
    {
        $sessionConfig = (string) file_get_contents(config_path('session.php'));

        $this->assertStringContainsString("env('SESSION_LOTTERY'", $sessionConfig);
    }

    public function test_database_session_prune_command_skips_non_database_drivers(): void
    {
        config(['session.driver' => 'file']);

        $this->artisan('sessions:prune-expired')
            ->expectsOutput('Skipping expired session pruning because the session driver is [file].')
            ->assertExitCode(0);
    }

    public function test_kernel_can_remove_global_middleware(): void
    {
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

        $this->assertInstanceOf(Kernel::class, $kernel);

        $kernel->pushMiddleware(InjectDebugbar::class);
        $kernel->removeMiddleware(InjectDebugbar::class);

        $middlewareProperty = new ReflectionProperty($kernel, 'middleware');
        $middlewareProperty->setAccessible(true);

        $this->assertNotContains(InjectDebugbar::class, $middlewareProperty->getValue($kernel));
    }
}
