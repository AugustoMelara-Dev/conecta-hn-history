<?php

namespace Tests;

use App\Application\Shared\Contracts\CommandValidatorRegistryInterface;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Shared\Middleware\CommandValidatorRegistry;
use App\Settings\BaseSettings;
use App\Settings\GeneralSettings;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use ReflectionObject;

abstract class TestCase extends BaseTestCase
{
    /**
     * Prepare a hermetic test environment.
     *
     * Notes:
     * - `tests/bootstrap.php` isolates MySQL databases per worker when Pest is
     *   executed with `--parallel`.
     * - This setup disables side-effects that commonly interfere with
     *   `RefreshDatabase`, such as the activity logger and CSRF protection.
     * - Tests that need model observers should opt back in explicitly.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        config(['activitylog.enabled' => false]);
        config(['logging.channels.security.channels' => ['security_file']]);
        config(['app.locale' => 'es']);

        Cache::flush();
        BaseSettings::clearRuntimeCache();
        app()->setLocale('es');
        Carbon::setLocale('es');
        setlocale(LC_TIME, 'es_HN.UTF-8', 'es_HN', 'es_ES.UTF-8', 'es_ES', 'es');

        $this->app->instance(
            CommandValidatorRegistryInterface::class,
            new CommandValidatorRegistry
        );

        $this->configureTestingRuntimePaths();
        $this->resetSharedTenantState();
        $this->primeSafeSystemSettings();
    }

    protected function tearDown(): void
    {
        BaseSettings::clearRuntimeCache();
        $this->resetSharedTenantState();

        parent::tearDown();
    }

    /**
     * Run a callback without model events.
     *
     * Example:
     * $this->withoutModelEvents(fn () => Tenant::factory()->create());
     */
    protected function withoutModelEvents(callable $callback): mixed
    {
        return Model::withoutEvents($callback);
    }

    /**
     * Initialize tenant context for tests with an explicit tenant id.
     */
    public function actingAsTenant(string|int $tenantId = 'tenant-1'): self
    {
        $context = $this->app->make(TenantContext::class);
        $context->reset();
        $context->setTenant($tenantId);

        return $this;
    }

    private function resetSharedTenantState(): void
    {
        $this->app->make(TenantContext::class)->reset();

        if ($this->app->has('currentTenant')) {
            $this->app->forgetInstance('currentTenant');
        }

        if ($this->app->has('current_tenant')) {
            $this->app->forgetInstance('current_tenant');
        }

        if (class_exists(Filament::class)) {
            try {
                $filamentRoot = Filament::getFacadeRoot();

                if ($filamentRoot !== null && str_starts_with(get_debug_type($filamentRoot), 'Mockery_')) {
                    return;
                }

                Filament::setTenant(null, isQuiet: true);
                Filament::setCurrentPanel(null);
                Filament::setServingStatus(false);
            } catch (\Throwable) {
                // Some policy tests replace the Filament manager with strict mocks.
                // Skipping the reset in that scenario keeps the global cleanup safe.
            }
        }
    }

    private function configureTestingRuntimePaths(): void
    {
        $compiledViewPath = config('view.compiled');

        if (! is_string($compiledViewPath) || $compiledViewPath === '') {
            return;
        }

        if (! is_dir($compiledViewPath) && ! @mkdir($compiledViewPath, 0755, true) && ! is_dir($compiledViewPath)) {
            throw new \RuntimeException("Unable to create compiled view path [{$compiledViewPath}].");
        }

        config(['view.compiled' => $compiledViewPath]);

        if (! $this->app->resolved('blade.compiler')) {
            return;
        }

        $compiler = $this->app['blade.compiler'];
        $reflection = new ReflectionObject($compiler);

        if (! $reflection->hasProperty('cachePath')) {
            return;
        }

        $cachePath = $reflection->getProperty('cachePath');
        $cachePath->setAccessible(true);
        $cachePath->setValue($compiler, $compiledViewPath);
    }

    private function primeSafeSystemSettings(): void
    {
        if (! class_exists(GeneralSettings::class)) {
            return;
        }

        try {
            $settings = $this->app->make(GeneralSettings::class);
            $settings->maintenance_mode = false;

            $this->app->instance(GeneralSettings::class, $settings);
        } catch (\Throwable) {
            // Some lower-level tests may boot before the settings store is available.
        }
    }
}
