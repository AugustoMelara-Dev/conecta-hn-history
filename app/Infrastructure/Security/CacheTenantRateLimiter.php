<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

final readonly class CacheTenantRateLimiter implements TenantRateLimiterInterface
{
    public function __construct(
        private RateLimiter $limiter,
    ) {}

    public function ensureNotExceeded(string $actionName, string $tenantId, int $maxAttempts = 100, int $decayMinutes = 1): void
    {
        $key = sprintf('%s:tenant:%s', $actionName, $tenantId);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $seconds = $this->limiter->availableIn($key);

            throw new ThrottleRequestsException(
                'Too many requests. Please try again in '.$seconds.' seconds.',
                null,
                ['Retry-After' => $seconds]
            );
        }

        $this->limiter->hit($key, $decayMinutes * 60);
    }
}
