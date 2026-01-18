<?php

declare(strict_types=1);

namespace App\Application\Identity\Decorators;

use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Application\Identity\Results\RegisterUserResult;

/**
 * IdempotentRegisterUserHandler - Pure DIP-Compliant Deduplication Guard
 *
 * Intercepts the command, checks for existing results, and manages distributed
 * locks to ensure "Exactly-Once" processing semantics.
 *
 * CRITICAL: Uses IdempotencyServiceInterface (Application contract)
 * instead of any Infrastructure implementation.
 */
final readonly class IdempotentRegisterUserHandler implements RegisterUserHandlerInterface
{
    public function __construct(
        private RegisterUserHandlerInterface $inner,
        private IdempotencyServiceInterface $service
    ) {}

    public function handle(RegisterUserCommand $command): RegisterUserResult
    {
        // 1. KEY RESOLUTION
        $key = $command->getIdempotencyKey();

        // 2. SHORT-CIRCUIT (Cache Hit)
        // If we already processed this exact key, return the saved result.
        $cached = $this->service->get($key);
        if ($cached instanceof RegisterUserResult) {
            return $cached;
        }

        // 3. ATOMIC EXECUTION WITH LOCKING
        // Uses framework-agnostic atomic() method instead of Lock object
        return $this->service->atomic($key, 5, function () use ($key, $command): RegisterUserResult {
            
            // 4. DOUBLE CHECK LOCKING
            // Another process might have finished while we waited for the lock.
            $doubleCheck = $this->service->get($key);
            if ($doubleCheck instanceof RegisterUserResult) {
                return $doubleCheck;
            }

            // 5. EXECUTION (The Core Handler)
            $result = $this->inner->handle($command);

            // 6. STORAGE (Result Caching)
            $this->service->store($key, $result, 86400); // 24h TTL

            return $result;
        });
    }
}
