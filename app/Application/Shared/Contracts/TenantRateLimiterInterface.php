<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

use App\Application\Shared\Common\Exceptions\RateLimitExceededException;

interface TenantRateLimiterInterface
{
    /**
     * Ensure the given action is not rate limited for the tenant.
     *
     * @throws RateLimitExceededException
     */
    public function ensureNotExceeded(string $actionName, string $tenantId, int $maxAttempts = 100, int $decayMinutes = 1): void;
}
