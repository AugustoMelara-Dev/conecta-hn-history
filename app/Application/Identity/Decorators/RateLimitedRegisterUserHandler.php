<?php

declare(strict_types=1);

namespace App\Application\Identity\Decorators;

use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Application\Identity\Results\RegisterUserResult;
use App\Application\Shared\Contracts\ClientContextProviderInterface;
use App\Application\Shared\Contracts\RateLimiterInterface;
use App\Domain\Common\Exceptions\TooManyRequestsException;

/**
 * RateLimitedRegisterUserHandler - Pure DIP-Compliant Shield
 *
 * CRITICAL FIX: Now uses RateLimiterInterface instead of concrete
 * Illuminate\Cache\RateLimiter class.
 *
 * This ensures:
 * - Zero framework coupling in Application layer
 * - Easy mocking for unit tests
 * - Possibility to swap implementations (Redis, DB, etc.)
 *
 * FAIR-USE POLICY: hit() is only called AFTER successful execution,
 * not in a finally block. Users are not penalized for server errors.
 */
final readonly class RateLimitedRegisterUserHandler implements RegisterUserHandlerInterface
{
    public function __construct(
        private RegisterUserHandlerInterface $inner,
        private RateLimiterInterface $rateLimiter,
        private ClientContextProviderInterface $contextProvider,
        private int $maxAttempts = 5,
        private int $decaySeconds = 3600,
    ) {}

    public function handle(RegisterUserCommand $command): RegisterUserResult
    {
        // 1. SEMANTIC KEY GENERATION (Context-Aware)
        $clientIdentity = $this->contextProvider->getClientIdentity();
        $key = "identity:register:client:{$clientIdentity}";

        // 2. FAIL FAST (Check rate limit before execution)
        if ($this->rateLimiter->tooManyAttempts($key, $this->maxAttempts)) {
            $seconds = $this->rateLimiter->availableIn($key);
            throw new TooManyRequestsException(
                "Too many registration attempts. Rate limit exceeded. Retry in {$seconds} seconds."
            );
        }

        // 3. EXECUTE (Let exceptions propagate without consuming quota)
        $result = $this->inner->handle($command);

        // 4. INCREMENT QUOTA (Only on success)
        // FAIR-USE: Moved OUTSIDE of try/finally block
        // If handle() throws, this line is never reached = fair
        $this->rateLimiter->hit($key, $this->decaySeconds);

        return $result;
    }
}
