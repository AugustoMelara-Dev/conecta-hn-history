<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class TenantQueueMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_restores_uuid_context_for_the_job_and_cleans_worker_state(): void
    {
        $tenant = Tenant::factory()->create();
        $context = app(TenantContext::class);
        $job = new class((string) $tenant->getKey())
        {
            public function __construct(public string $tenantId) {}
        };

        $result = (new TenantQueueMiddleware)->handle($job, function () use ($context, $tenant): string {
            self::assertSame((string) $tenant->getKey(), (string) $context->id());
            self::assertSame((string) $tenant->getKey(), (string) app('currentTenant')->getKey());

            return 'processed';
        });

        self::assertSame('processed', $result);
        self::assertFalse($context->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
        self::assertFalse(app()->bound('current_tenant'));
    }

    public function test_it_cleans_context_when_the_job_throws(): void
    {
        $tenant = Tenant::factory()->create();
        $context = app(TenantContext::class);
        $job = new class((string) $tenant->getKey())
        {
            public function __construct(public string $tenantId) {}
        };

        try {
            (new TenantQueueMiddleware)->handle($job, function () use ($context): never {
                self::assertTrue($context->isInitialized());

                throw new RuntimeException('Job failed.');
            });

            self::fail('The job exception should be propagated.');
        } catch (RuntimeException $exception) {
            self::assertSame('Job failed.', $exception->getMessage());
        }

        self::assertFalse($context->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
        self::assertFalse(app()->bound('current_tenant'));
    }

    public function test_missing_tenant_fails_closed_and_clears_stale_worker_state(): void
    {
        $staleTenant = Tenant::factory()->create();
        $context = app(TenantContext::class);
        $context->setTenant((string) $staleTenant->getKey());
        app()->instance('currentTenant', $staleTenant);
        app()->instance('current_tenant', $staleTenant);
        $job = new class('00000000-0000-4000-8000-000000000000')
        {
            public function __construct(public string $tenantId) {}
        };
        $nextCalled = false;

        try {
            (new TenantQueueMiddleware)->handle($job, function () use (&$nextCalled): void {
                $nextCalled = true;
            });

            self::fail('A missing tenant should fail the job.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('not found', $exception->getMessage());
        }

        self::assertFalse($nextCalled);
        self::assertFalse($context->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
        self::assertFalse(app()->bound('current_tenant'));
    }

    public function test_reused_worker_never_observes_the_previous_jobs_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $context = app(TenantContext::class);
        $middleware = new TenantQueueMiddleware;

        foreach ([$tenantA, $tenantB] as $tenant) {
            $job = new class((string) $tenant->getKey())
            {
                public function __construct(public string $tenantId) {}
            };

            $middleware->handle($job, function () use ($context, $tenant): void {
                self::assertSame((string) $tenant->getKey(), (string) $context->id());
                self::assertSame((string) $tenant->getKey(), (string) app('currentTenant')->getKey());
                self::assertSame((string) $tenant->getKey(), (string) app('current_tenant')->getKey());
            });

            self::assertFalse($context->isInitialized());
            self::assertFalse(app()->bound('currentTenant'));
            self::assertFalse(app()->bound('current_tenant'));
        }
    }
}
