<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Results\VerifyOtpResult;
use App\Domain\Shared\Contracts\RateLimitServiceInterface;
use App\Application\Shared\Common\Exceptions\RateLimitExceededException;

final readonly class VerifyOtpRateLimitDecorator implements VerifyOtpHandlerInterface
{
    public function __construct(
        private VerifyOtpHandlerInterface $inner,
        private RateLimitServiceInterface $rateLimiter,
        private int $maxAttempts = 5,
        private int $decaySeconds = 60
    ) {}

    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        // Key: verify_otp:{tenant}:{ip}
        $key = sprintf('verify_otp:%s:%s', $command->tenantId, $command->ipAddress);

        if ($this->rateLimiter->tooManyAttempts($key, $this->maxAttempts)) {
            throw new RateLimitExceededException('Demasiados intentos. Intente más tarde.');
        }

        $this->rateLimiter->hit($key, $this->decaySeconds);

        return $this->inner->handle($command);
    }
}
