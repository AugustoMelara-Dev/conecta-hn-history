<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Decorators\IdempotentCreateOrderHandler;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Application\Shared\Common\Exceptions\ConcurrentRequestException;
use App\Application\Shared\Contracts\IdempotencyServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateOrderIdempotencyTest extends TestCase
{
    #[Test]
    public function it_survives_replays_and_executes_the_core_handler_only_once(): void
    {
        $expectedResult = new CreateOrderResult(
            id: 999,
            orderNumber: 'ORD-ATOMIC-01',
            totalAmount: 1500.00,
            status: 'pending',
            itemsCount: 2,
            currency: 'HNL',
            items: [],
        );

        $coreHandler = new RecordingCreateOrderHandler($expectedResult);
        $service = new InMemoryIdempotencyService;
        $decorator = new IdempotentCreateOrderHandler($coreHandler, $service);

        $command = new CreateOrderCommand(
            tenantId: 'tenant-1',
            locationId: 'loc-primary',
            customerId: 'customer-10',
            currency: 'HNL',
            items: [['product_id' => 1, 'quantity' => 1]],
            idempotencyKey: 'atomic-key-XYZ',
        );

        $resultA = $decorator->handle($command);
        $resultB = $decorator->handle($command);
        $resultC = $decorator->handle($command);

        self::assertSame(1, $coreHandler->calls);
        self::assertSame($expectedResult->toArray(), $resultA->toArray());
        self::assertSame($resultA->toArray(), $resultB->toArray());
        self::assertSame($resultB->toArray(), $resultC->toArray());
    }
}

final class RecordingCreateOrderHandler implements CreateOrderHandlerInterface
{
    public int $calls = 0;

    public function __construct(private readonly CreateOrderResult $result) {}

    public function handle(CreateOrderCommand $command): CreateOrderResult
    {
        $this->calls++;

        return $this->result;
    }
}

final class InMemoryIdempotencyService implements IdempotencyServiceInterface
{
    /** @var array<string, mixed> */
    private array $results = [];

    /** @var array<string, string> */
    private array $states = [];

    public function get(string $key): mixed
    {
        return $this->results[$key] ?? null;
    }

    public function getState(string $key): ?string
    {
        return $this->states[$key] ?? null;
    }

    public function registerProcessing(string $key, int $ttlSeconds = 60): void
    {
        if (isset($this->states[$key])) {
            throw ConcurrentRequestException::forKey($key);
        }

        $this->states[$key] = 'PROCESSING';
    }

    public function store(string $key, mixed $value, int $ttlSeconds = 86400): void
    {
        $this->results[$key] = $value;
        $this->states[$key] = 'COMPLETED';
    }
}
