<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Sales\Commands\SyncCheckoutSessionCommand;
use App\Application\Sales\Contracts\CheckoutSessionPortInterface;
use App\Application\Sales\Handlers\SyncCheckoutSessionHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SyncCheckoutSessionTest extends TestCase
{
    #[Test]
    public function it_delegates_checkout_session_sync_to_the_port(): void
    {
        $port = new RecordingCheckoutSessionPort;
        $handler = new SyncCheckoutSessionHandler($port);

        $command = new SyncCheckoutSessionCommand(
            'tenant-1',
            'session-123',
            [['product_id' => 1, 'quantity' => 2]],
            150.50,
            99,
        );

        $handler->handle($command);

        self::assertSame([
            [
                'tenant_id' => 'tenant-1',
                'session_id' => 'session-123',
                'items' => [['product_id' => 1, 'quantity' => 2]],
                'subtotal' => 150.5,
                'user_id' => 99,
            ],
        ], $port->upserts);
    }
}

final class RecordingCheckoutSessionPort implements CheckoutSessionPortInterface
{
    /** @var array<int, array{tenant_id: string|int, session_id: string, items: array<int, array<string, int>>, subtotal: float, user_id: string|int|null}> */
    public array $upserts = [];

    public function upsertSession(string|int $tenantId, string $sessionId, array $items, float $subtotal, string|int|null $userId): void
    {
        $this->upserts[] = [
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'items' => $items,
            'subtotal' => $subtotal,
            'user_id' => $userId,
        ];
    }

    public function markAsAbandoned(int $timeoutMinutes): array
    {
        return [];
    }

    public function markAsConverted(string|int $tenantId, string $sessionId): void {}
}
