<?php

declare(strict_types=1);

namespace App\Application\Tenant\Decorators;

use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Application\Tenant\Commands\RegisterTenantCommand;
use App\Application\Tenant\Handlers\RegisterTenantHandlerInterface;
use App\Application\Tenant\Results\RegisterTenantResult;
use App\Domain\Common\Exceptions\ConcurrencyException;

/**
 * IdempotentRegisterTenantHandler (Decorator)
 *
 * Guarantees "Exactly-Once" processing for Tenant Registration.
 */
final readonly class IdempotentRegisterTenantHandler implements RegisterTenantHandlerInterface
{
    public function __construct(
        private RegisterTenantHandlerInterface $inner,
        private IdempotencyServiceInterface $service
    ) {}

    public function handle(RegisterTenantCommand $command): RegisterTenantResult
    {
        $key = $command->getIdempotencyKey();

        // 1. Check Cache
        $cachedResult = $this->service->get($key);
        if ($cachedResult instanceof RegisterTenantResult) {
            return $cachedResult;
        }

        // 2. Lock
        $lock = $this->service->lock($key);

        if (! $lock->get()) {
            throw new ConcurrencyException('Tenant registration in progress.');
        }

        try {
            // 3. Double Check
            $cachedResult = $this->service->get($key);
            if ($cachedResult instanceof RegisterTenantResult) {
                return $cachedResult;
            }

            // 4. Execute
            $result = $this->inner->handle($command);

            // 5. Store
            $this->service->store($key, $result);

            return $result;

        } finally {
            $lock->release();
        }
    }
}
