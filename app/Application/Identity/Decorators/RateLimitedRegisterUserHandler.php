<?php

declare(strict_types=1);

namespace App\Application\Identity\Decorators;

use App\Application\Common\Interfaces\ClientContextProviderInterface;
use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Application\Identity\Results\RegisterUserResult;
use Illuminate\Cache\RateLimiter;
use App\Domain\Common\Exceptions\TooManyRequestsException;

/**
 * RateLimitedRegisterUserHandler - Protocol Agnostic Shield
 * 
 * First line of defense against spam and brute-force attacks.
 * Now decoupled from HTTP via ClientContextProviderInterface.
 */
final readonly class RateLimitedRegisterUserHandler implements RegisterUserHandlerInterface
{
    public function __construct(
        private RegisterUserHandlerInterface $inner,
        private RateLimiter $rateLimiter,
        private ClientContextProviderInterface $contextProvider
    ) {}

    public function handle(RegisterUserCommand $command): RegisterUserResult
    {
        // 1. SEMANTIC KEY GENERATION (Context-Aware)
        $clientIdentity = $this->contextProvider->getClientIdentity();
        $key = "identity:register:client:{$clientIdentity}";
        
        // 2. FAIL FAST (Business Limit: 5 per hour)
        $maxAttempts = 5;
        $decaySeconds = 3600;

        if ($this->rateLimiter->tooManyAttempts($key, $maxAttempts)) {
            $seconds = $this->rateLimiter->availableIn($key);
            throw new TooManyRequestsException("Too many registration attempts. standard business limit exceeded. Retry in {$seconds} seconds.");
        }

        // 3. EXECUTE & INCREMENT
        try {
            return $this->inner->handle($command);
        } finally {
             $this->rateLimiter->hit($key, $decaySeconds);
        }
    }
}
