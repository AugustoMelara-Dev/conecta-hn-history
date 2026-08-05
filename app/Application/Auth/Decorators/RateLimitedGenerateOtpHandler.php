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
        $prefix = 'otp_generation:'.$command->tenantId;
        $identifierKey = $prefix.':identifier:'.hash('sha256', mb_strtolower(trim($command->identifier)));
        // Cross-tenant bucket prevents rotating attacker-controlled tenant UUIDs.
        $ipKey = 'otp_generation:ip:'.hash('sha256', $command->ipAddress);
        $identifierLimited = $this->rateLimiter->hitAndCheck(
            $identifierKey,
            $this->maxRequestsPerHour,
            $this->decaySeconds,
        );
        $ipLimited = $this->rateLimiter->hitAndCheck(
            $ipKey,
            $this->maxRequestsPerHour * 3,
            $this->decaySeconds,
        );

        if ($identifierLimited || $ipLimited) {
            $retryAfter = max(
                $identifierLimited ? $this->rateLimiter->availableIn($identifierKey) : 0,
                $ipLimited ? $this->rateLimiter->availableIn($ipKey) : 0,
            );
            throw TooManyOtpRequestsException::forUser($command->identifier, $retryAfter);
        }

        $this->inner->handle($command);
    }
}
