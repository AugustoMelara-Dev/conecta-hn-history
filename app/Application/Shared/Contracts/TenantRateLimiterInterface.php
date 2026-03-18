<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface TenantRateLimiterInterface
{
    /**
     * Ensure the given action is not rate limited for the tenant.
     *
     * @throws \App\Application\Shared\Common\Exceptions\RateLimitExceededException
     */
    public function ensureNotExceeded(string $actionName, string $tenantId, int $maxAttempts = 100, int $decayMinutes = 1): void;
}
