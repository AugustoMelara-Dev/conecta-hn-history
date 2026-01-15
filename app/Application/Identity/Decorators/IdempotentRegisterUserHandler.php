<?php

declare(strict_types=1);

namespace App\Application\Identity\Decorators;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Application\Identity\Results\RegisterUserResult;
use App\Infrastructure\Common\Idempotency\IdempotencyService;
use App\Application\Common\Interfaces\IdempotentCommandInterface;

/**
 * IdempotentRegisterUserHandler - The Deduplication Guard
 * 
 * Intercepts the command, checks for existing results, and manages distributed locks
 * to ensure "Exactly-Once" processing semantics.
 */
final readonly class IdempotentRegisterUserHandler implements RegisterUserHandlerInterface
{
    public function __construct(
        private RegisterUserHandlerInterface $inner,
        private IdempotencyService $service
    ) {}

    public function handle(RegisterUserCommand $command): RegisterUserResult
    {
        // 1. KEY RESOLUTION
        // The command must implementation IdempotentCommandInterface
        // In the future, we might use a dedicated interface check, but RegisterUserCommand adheres to it.
        $key = $command->getIdempotencyKey();

        // 2. SHORT-CIRCUIT (Cache Hit)
        // If we already processed this exact ID, return the saved result.
        /** @var RegisterUserResult|null $cached */
        $cached = $this->service->get($key);
        if ($cached) {
            return $cached;
        }

        // 3. ATOMIC LOCK (Concurrency Control)
        $lock = $this->service->lock($key);

        // Block for up to 5 seconds waiting for the lock
        return $lock->block(5, function () use ($key, $command) {
            
            // 4. DOUBLE CHECK LOCKING
            // Another process might have finished while we waited for the lock.
            /** @var RegisterUserResult|null $doubleCheck */
            $doubleCheck = $this->service->get($key);
            if ($doubleCheck) {
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
