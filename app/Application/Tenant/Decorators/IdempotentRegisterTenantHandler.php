<?php

declare(strict_types=1);

namespace App\Application\Tenant\Decorators;

use App\Application\Shared\Common\Exceptions\ConcurrentRequestException;
use App\Application\Shared\Contracts\IdempotencyServiceInterface;
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

        try {
            $this->service->registerProcessing($key);
        } catch (ConcurrentRequestException) {
            $cachedResult = $this->service->get($key);
            if ($cachedResult instanceof RegisterTenantResult) {
                return $cachedResult;
            }

            throw new ConcurrencyException('Tenant registration in progress.');
        }

        $cachedResult = $this->service->get($key);
        if ($cachedResult instanceof RegisterTenantResult) {
            return $cachedResult;
        }

        $result = $this->inner->handle($command);

        $this->service->store($key, $result);

        return $result;
    }
}
