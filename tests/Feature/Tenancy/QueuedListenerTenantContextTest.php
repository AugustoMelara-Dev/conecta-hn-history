<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Application\Billing\Listeners\PromotePaymentEvidence;
use App\Domain\Billing\Events\PaymentReported;
use App\Domain\Identity\Events\UserRegisteredViaSocial;
use App\Domain\Interactions\Events\InteractionRecorded;
use App\Domain\Shared\Services\TenantContext;
use App\Events\Appointment\AppointmentCreated;
use App\Events\Order\OrderCancelled;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Listeners\Interactions\ProcessInteractionSideEffects;
use App\Listeners\OrderCancelledListener;
use App\Listeners\SendAppointmentConfirmation;
use App\Listeners\SendWelcomeEmail;
use App\Models\Tenant;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use stdClass;
use Tests\TestCase;

final class QueuedListenerTenantContextTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function appointment_listener_restores_uuid_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        $event = new AppointmentCreated('appointment-1', (string) $tenant->getKey());

        $this->assertListenerMiddlewareRestoresTenant(
            new SendAppointmentConfirmation,
            $event,
            (string) $tenant->getKey(),
        );
    }

    #[Test]
    public function interaction_listener_restores_uuid_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        $event = new InteractionRecorded(
            interactionId: 'interaction-1',
            tenantId: (string) $tenant->getKey(),
            occurredAt: new DateTimeImmutable,
        );
        $listener = (new ReflectionClass(ProcessInteractionSideEffects::class))
            ->newInstanceWithoutConstructor();

        $this->assertListenerMiddlewareRestoresTenant($listener, $event, (string) $tenant->getKey());
    }

    #[Test]
    public function payment_evidence_listener_restores_uuid_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        $event = new PaymentReported(
            paymentId: 'payment-1',
            tenantId: (string) $tenant->getKey(),
            amountInCents: 49900,
            currency: 'HNL',
            reference: 'reference-1',
        );
        $listener = (new ReflectionClass(PromotePaymentEvidence::class))
            ->newInstanceWithoutConstructor();

        $this->assertListenerMiddlewareRestoresTenant($listener, $event, (string) $tenant->getKey());
    }

    #[Test]
    public function cancellation_listener_restores_uuid_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        $event = OrderCancelled::create(
            tenantId: (string) $tenant->getKey(),
            orderId: 'order-1',
            orderNumber: 'ORD-1',
            reason: 'customer_request',
        );

        $this->assertListenerMiddlewareRestoresTenant(
            new OrderCancelledListener,
            $event,
            (string) $tenant->getKey(),
        );
    }

    #[Test]
    public function social_welcome_listener_restores_uuid_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();
        $event = UserRegisteredViaSocial::now(
            userId: 'user-1',
            tenantId: (string) $tenant->getKey(),
            email: 'welcome@example.test',
            provider: 'google',
        );
        $listener = (new ReflectionClass(SendWelcomeEmail::class))
            ->newInstanceWithoutConstructor();

        $this->assertListenerMiddlewareRestoresTenant($listener, $event, (string) $tenant->getKey());
    }

    #[Test]
    public function discovered_queued_listeners_are_registered_exactly_once(): void
    {
        $events = app('events');

        self::assertCount(1, $events->getListeners(AppointmentCreated::class));
        self::assertCount(1, $events->getListeners(InteractionRecorded::class));
    }

    private function assertListenerMiddlewareRestoresTenant(
        object $listener,
        object $event,
        string $tenantId,
    ): void {
        /** @var array<int, TenantQueueMiddleware> $middleware */
        $middleware = $listener->middleware($event);

        self::assertCount(1, $middleware);
        self::assertInstanceOf(TenantQueueMiddleware::class, $middleware[0]);

        $context = app(TenantContext::class);
        $result = $middleware[0]->handle(new stdClass, function () use ($context, $tenantId): string {
            self::assertSame($tenantId, (string) $context->id());
            self::assertSame($tenantId, (string) app('currentTenant')->getKey());

            return 'processed';
        });

        self::assertSame('processed', $result);
        self::assertFalse($context->isInitialized());
        self::assertFalse(app()->bound('currentTenant'));
    }
}
