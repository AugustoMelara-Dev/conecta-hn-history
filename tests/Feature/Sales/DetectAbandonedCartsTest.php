<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Sales\Commands\DetectAbandonedCartsCommand;
use App\Application\Sales\Contracts\CheckoutSessionPortInterface;
use App\Application\Sales\Handlers\DetectAbandonedCartsHandler;
use App\Domain\Sales\Events\CartAbandoned;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DetectAbandonedCartsTest extends TestCase
{
    #[Test]
    public function it_fetches_abandoned_carts_and_dispatches_domain_events(): void
    {
        $port = new StubCheckoutSessionPort([
            [
                'id' => 10,
                'tenant_id' => 'tenant-1',
                'session_id' => 'session-123',
                'user_id' => 99,
                'subtotal' => 150.0,
            ],
        ]);
        $dispatcher = new RecordingEventDispatcher;

        $handler = new DetectAbandonedCartsHandler($port, $dispatcher);

        $handler->handle(new DetectAbandonedCartsCommand(30));

        self::assertSame([30], $port->abandonedTimeouts);
        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(CartAbandoned::class, $dispatcher->events[0]);

        /** @var CartAbandoned $event */
        $event = $dispatcher->events[0];

        self::assertSame(10, $event->checkoutSessionId);
        self::assertSame('tenant-1', $event->tenantId);
        self::assertSame('session-123', $event->sessionId);
        self::assertSame(99, $event->userId);
        self::assertSame(150.0, $event->subtotal);
    }
}

final class StubCheckoutSessionPort implements CheckoutSessionPortInterface
{
    /** @var array<int, int> */
    public array $abandonedTimeouts = [];

    /**
     * @param  array<int, array{id: int, tenant_id: string, session_id: string, user_id: string|int|null, subtotal: float}>  $abandonedSessions
     */
    public function __construct(private readonly array $abandonedSessions) {}

    public function upsertSession(string|int $tenantId, string $sessionId, array $items, float $subtotal, string|int|null $userId): void {}

    public function markAsAbandoned(int $timeoutMinutes): array
    {
        $this->abandonedTimeouts[] = $timeoutMinutes;

        return $this->abandonedSessions;
    }

    public function markAsConverted(string|int $tenantId, string $sessionId): void {}
}

final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): void
    {
        $this->events[] = $event;
    }
}
