<?php

namespace Tests;

use App\Application\Shared\Contracts\CommandValidatorRegistryInterface;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Shared\Middleware\CommandValidatorRegistry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

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
        app()->setLocale('es');
        Carbon::setLocale('es');
        setlocale(LC_TIME, 'es_HN.UTF-8', 'es_HN', 'es_ES.UTF-8', 'es_ES', 'es');

        $this->app->instance(
            CommandValidatorRegistryInterface::class,
            new CommandValidatorRegistry
        );
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
}
