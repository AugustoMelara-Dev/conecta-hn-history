<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Common\Outbox\LaravelEventMessagePublisher;
use App\Infrastructure\Console\Commands\ProcessOutboxCommand;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

final class OutboxTenantContextPropagationTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_dispatch_restores_each_tenant_and_cleans_reused_worker_state(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $seen = [];
        $context = app(TenantContext::class);

        Event::listen(TestOutboxTenantEvent::class, function (TestOutboxTenantEvent $event) use (&$seen, $context): void {
            $seen[] = [
                'event' => $event->tenantId,
                'context' => $context->id(),
                'container' => (string) app('currentTenant')->getKey(),
            ];
        });

        foreach ([$tenantA, $tenantB] as $tenant) {
            app(LaravelEventMessagePublisher::class)->publish(
                TestOutboxTenantEvent::class,
                json_encode([
                    'meta' => ['tenant_id' => (string) $tenant->getKey()],
                    'data' => ['tenantId' => (string) $tenant->getKey()],
                ], JSON_THROW_ON_ERROR),
            );

            self::assertFalse($context->isInitialized());
            self::assertFalse(app()->bound('currentTenant'));
            self::assertFalse(app()->bound('current_tenant'));
        }

        self::assertSame([
            [
                'event' => (string) $tenantA->getKey(),
                'context' => (string) $tenantA->getKey(),
                'container' => (string) $tenantA->getKey(),
            ],
            [
                'event' => (string) $tenantB->getKey(),
                'context' => (string) $tenantB->getKey(),
                'container' => (string) $tenantB->getKey(),
            ],
        ], $seen);
    }

    public function test_missing_outbox_tenant_fails_closed_and_cleans_stale_state(): void
    {
        $stale = Tenant::factory()->create();
        $context = app(TenantContext::class);
        $context->setTenant((string) $stale->getKey());
        app()->instance('currentTenant', $stale);

        try {
            app(LaravelEventMessagePublisher::class)->publish(
                TestOutboxTenantEvent::class,
                json_encode([
                    'meta' => ['tenant_id' => '00000000-0000-4000-8000-000000000000'],
                    'data' => ['tenantId' => '00000000-0000-4000-8000-000000000000'],
                ], JSON_THROW_ON_ERROR),
            );

            self::fail('A missing outbox tenant must not dispatch.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('not found', $exception->getMessage());
        }

        self::assertFalse($context->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
        self::assertFalse(app()->bound('current_tenant'));
    }

    public function test_primary_and_recovery_outbox_paths_share_the_canonical_context_runner(): void
    {
        $source = file_get_contents((new ReflectionClass(ProcessOutboxCommand::class))->getFileName());

        self::assertSame(2, substr_count($source, 'dispatchWithTenantContext($message, $event, $dispatcher)'));
        self::assertStringContainsString('OutboxTenantContextRunner::class', $source);
    }
}

final readonly class TestOutboxTenantEvent
{
    public function __construct(public string $tenantId) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromPrimitives(array $data): self
    {
        return new self((string) ($data['tenantId'] ?? ''));
    }
}
