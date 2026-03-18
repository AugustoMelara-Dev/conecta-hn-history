<?php

declare(strict_types=1);

namespace App\Application\Sales\Decorators;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Application\Shared\Common\Exceptions\ConcurrentRequestException;
use App\Application\Shared\Contracts\IdempotencyServiceInterface;
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

        try {
            $this->service->registerProcessing($key);
        } catch (ConcurrentRequestException) {
            $cachedResult = $this->service->get($key);
            if ($cachedResult instanceof CreateOrderResult) {
                return $cachedResult;
            }

            throw new ConcurrencyException('Order processing already in progress for this request.');
        }

        // 3. Double-Check inside processing window (Critical for high concurrency)
        $cachedResult = $this->service->get($key);
        if ($cachedResult instanceof CreateOrderResult) {
            return $cachedResult;
        }

        // 4. Execution
        $result = $this->inner->handle($command);

        // 5. Store Result and mark COMPLETED for future replays
        $this->service->store($key, $result);

        return $result;
    }
}
