<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Inventory\Contracts\InventoryHoldPortInterface;
use App\Application\Sales\Commands\HoldCartInventoryCommand;
use App\Application\Sales\Handlers\HoldCartInventoryHandler;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HoldCartInventoryTest extends TestCase
{
    #[Test]
    public function it_delegates_hold_creation_to_the_inventory_port(): void
    {
        $port = new RecordingInventoryHoldPort;
        $transactionManager = new InlineTransactionManager;
        $handler = new HoldCartInventoryHandler($port, $transactionManager);

        $command = new HoldCartInventoryCommand(
            'tenant-1',
            'location-1',
            'session-123',
            [['product_id' => 1, 'quantity' => 2]],
        );

        $handler->handle($command);

        self::assertSame(1, $transactionManager->transactions);
        self::assertSame([
            [
                'tenant_id' => 'tenant-1',
                'location_id' => 'location-1',
                'session_id' => 'session-123',
                'product_id' => 1,
                'quantity' => 2.0,
                'expires_in_minutes' => 15,
            ],
        ], $port->reservations);
    }
}

final class RecordingInventoryHoldPort implements InventoryHoldPortInterface
{
    /** @var array<int, array{tenant_id: string, location_id: string, session_id: string, product_id: int, quantity: float, expires_in_minutes: int}> */
    public array $reservations = [];

    public function reserve(
        string $tenantId,
        string $locationId,
        string $sessionId,
        int $productId,
        float $quantity,
        int $expiresInMinutes = 15
    ): void {
        $this->reservations[] = [
            'tenant_id' => $tenantId,
            'location_id' => $locationId,
            'session_id' => $sessionId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'expires_in_minutes' => $expiresInMinutes,
        ];
    }

    public function commit(string $tenantId, string $sessionId, string|int|null $referenceId = null): void {}

    public function releaseExpired(): int
    {
        return 0;
    }
}

final class InlineTransactionManager implements TransactionManagerInterface
{
    public int $transactions = 0;

    public function transaction(callable $callback): mixed
    {
        $this->transactions++;

        return $callback();
    }
}
