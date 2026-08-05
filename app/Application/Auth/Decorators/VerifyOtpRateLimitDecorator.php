<?php

declare(strict_types=1);

namespace App\Application\Auth\Decorators;

use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Results\VerifyOtpResult;
use App\Domain\Shared\Contracts\RateLimitServiceInterface;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

final class VerifyOtpRateLimitDecorator implements VerifyOtpHandlerInterface
{
    public function __construct(
        private VerifyOtpHandlerInterface $inner,
        private RateLimitServiceInterface $rateLimiter,
        private int $maxAttempts = 5,
        private int $decaySeconds = 60
    ) {}

    public function handle(VerifyOtpCommand $command): VerifyOtpResult
    {
        $prefix = 'verify_otp:'.$command->tenantId;
        $identifierKey = $prefix.':identifier:'.hash('sha256', mb_strtolower(trim($command->identifier)));
        $ipKey = 'verify_otp:ip:'.hash('sha256', $command->ipAddress);
        $identifierLimited = $this->rateLimiter->hitAndCheck(
            $identifierKey,
            $this->maxAttempts,
            $this->decaySeconds,
        );
        $ipLimited = $this->rateLimiter->hitAndCheck(
            $ipKey,
            $this->maxAttempts * 4,
            $this->decaySeconds,
        );

        if ($identifierLimited || $ipLimited) {
            throw new ThrottleRequestsException('Demasiados intentos. Intente más tarde.');
        }

        return $this->inner->handle($command);
    }
}
