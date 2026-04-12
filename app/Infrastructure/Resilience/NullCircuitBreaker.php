<?php

declare(strict_types=1);

namespace App\Infrastructure\Resilience;

use App\Application\Shared\Contracts\CircuitBreakerInterface;

final class NullCircuitBreaker implements CircuitBreakerInterface
{
    public function isAvailable(string $serviceName): bool
    {
        return true;
    }

    public function reportSuccess(string $serviceName): void
    {
        // No-op fallback for testing/CI when distributed circuit state is unavailable.
    }

    public function reportFailure(string $serviceName): void
    {
        // No-op fallback for testing/CI when distributed circuit state is unavailable.
    }
}
