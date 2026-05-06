<?php

declare(strict_types=1);

namespace App\Application\Sales\Decorators;

use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Domain\Common\Exceptions\ConcurrencyException;

/**
 * IdempotentCreateOrderHandler (Decorator)
 *
 * Guarantees "Exactly-Once" processing for Orders.
 */
final readonly class IdempotentCreateOrderHandler implements CreateOrderHandlerInterface
{
    public function __construct(
        private CreateOrderHandlerInterface $inner,
        private IdempotencyServiceInterface $service
    ) {}

    public function handle(CreateOrderCommand $command): CreateOrderResult
    {
        $key = $command->getIdempotencyKey();

        // 1. Check for Cached Result (Fast Path)
        $cachedResult = $this->service->get($key);
        if ($cachedResult instanceof CreateOrderResult) {
            return $cachedResult;
        }

        // 2. Acquire Atomic Lock
        $lock = $this->service->lock($key);

        if (! $lock->get()) {
            // If we can't get lock, it means another process is working on this key.
            // We check once more if result appeared (Double-Check Locking variant via Cache)
            // or we throw ConcurrencyException / 429.
            // For now, simple concurrency error.
            throw new ConcurrencyException('Order processing already in progress for this request.');
        }

        try {
            // 3. Double-Check inside Lock (Critical for high concurrency)
            $cachedResult = $this->service->get($key);
            if ($cachedResult instanceof CreateOrderResult) {
                return $cachedResult;
            }

            // 4. Execution
            $result = $this->inner->handle($command);

            // 5. Store Result (Release Lock implicitly by expiration or logic, but we store result)
            $this->service->store($key, $result);

            return $result;

        } finally {
            $lock->release();
        }
    }
}
