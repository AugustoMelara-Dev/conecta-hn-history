<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface TenantRateLimiterInterface
{
    /**
     * Ensure the given action is not rate limited for the tenant.
     *
     * @param  string  $actionName
     * @param  string  $tenantId
     * @param  int  $maxAttempts
     * @param  int  $decayMinutes
     * @throws \Illuminate\Http\Exceptions\ThrottleRequestsException
     */
    public function ensureNotExceeded(string $actionName, string $tenantId, int $maxAttempts = 100, int $decayMinutes = 1): void;
}
