<?php

declare(strict_types=1);

namespace App\Application\Shared\Middleware;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * ValidationMiddleware - Intercepts Commands before Handling
 *
 * Implements a "Convention over Configuration" pipeline step.
 * Scans for a matching Validator class and enforces rules strictly.
 */
class ValidationMiddleware
{
    public function handle(object $command, Closure $next): mixed
    {
        // 1. Resolve Validator Class Name (Convention: Command + Validator replacement)
        // Adjust logic to map App\Application\Commands\X -> App\Application\Validators\XValidator
        // Current: App\Application\Identity\Commands\RegisterUserCommand
        // Target:  App\Application\Identity\Validators\RegisterUserValidator

        $commandClass = get_class($command);
        if (! str_contains($commandClass, '\\Commands\\') || ! str_ends_with($commandClass, 'Command')) {
            return $next($command);
        }
        $validatorClass = str_replace(
            ['\\Commands\\', 'Command'],
            ['\\Validators\\', 'Validator'],
            $commandClass
        );

        // 2. Skip if no validator exists (Opt-in validation)
        if (! class_exists($validatorClass)) {
            return $next($command);
        }

        // 3. Resolve Validator Instance
        $validatorInstance = app($validatorClass);

        // 4. Extract Rules (Contract: rules($command))
        if (! method_exists($validatorInstance, 'rules')) {
            return $next($command);
        }

        $rules = $validatorInstance->rules($command);

        // 5. Execute Validation
        // Convert strict DTO object to array for Validator compatibility
        $payload = json_decode(json_encode($command), true);

        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // 6. Pass to next middleware/handler
        return $next($command);
    }
}
