<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Domain\Auth\Exceptions\TooManyOtpRequestsException;
use App\Domain\Shared\Contracts\RateLimitServiceInterface;

/**
 * RateLimitDecorator - The Security Shield
 *
 * Enforces throughput limits per tenant/user.
 * Prevents abuse before it reaches expensive business logic or validation.
 */
final readonly class RateLimitedGenerateOtpHandler implements GenerateOtpHandlerInterface
{
    public function __construct(
        private GenerateOtpHandlerInterface $inner,
        private RateLimitServiceInterface $rateLimiter,
        private int $maxRequestsPerHour,
        private int $decaySeconds
    ) {}

    public function handle(GenerateOtpCommand $command): void
    {
        // COMPOUND KEY: Tenant + IP protects against distributed attacks from single source
        $key = "otp_generation:{$command->tenantId}:{$command->ipAddress}";

        if ($this->rateLimiter->hitAndCheck($key, $this->maxRequestsPerHour, $this->decaySeconds)) {
            $retryAfter = $this->rateLimiter->availableIn($key);
            throw TooManyOtpRequestsException::forUser($command->identifier, $retryAfter);
        }

        $this->inner->handle($command);
    }
}
