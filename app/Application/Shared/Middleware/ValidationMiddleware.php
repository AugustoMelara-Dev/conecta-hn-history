<?php

declare(strict_types=1);

namespace App\Application\Shared\Middleware;

use App\Application\Shared\Contracts\CommandValidatorRegistryInterface;
use App\Application\Shared\Contracts\ValidatorFactoryInterface;
use App\Application\Shared\Exceptions\CommandValidationException;
use Closure;

/**
 * ValidationMiddleware - Intercepts Commands before Handling
 *
 * Implements a "Registry over Convention" pipeline step.
 * Resolves validators via an explicit registry — no Service Locator.
 */
final readonly class ValidationMiddleware
{
    public function __construct(
        private ValidatorFactoryInterface $validator,
        private CommandValidatorRegistryInterface $registry,
    ) {}

    public function handle(object $command, Closure $next): mixed
    {
        $commandClass = get_class($command);

        // 2. Resolve via explicit registry (no Service Locator, no convention string magic)
        $validatorInstance = $this->registry->findFor($commandClass);

        if ($validatorInstance === null) {
            return $next($command);
        }

        // 4. Extract Rules (Contract: rules($command))
        if (! method_exists($validatorInstance, 'rules')) {
            return $next($command);
        }

        $rules = $validatorInstance->rules($command);

        // 5. Execute Validation
        // Convert strict DTO object to array for Validator compatibility
        $payload = json_decode(json_encode($command), true);

        // ValidatorFactoryInterface::validate() throws CommandValidationException on failure
        $this->validator->validate($payload, $rules);

        // 6. Pass to next middleware/handler
        return $next($command);
    }
}
