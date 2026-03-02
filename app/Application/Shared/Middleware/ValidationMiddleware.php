<?php

declare(strict_types=1);

namespace App\Application\Shared\Middleware;

use Closure;
use App\Application\Shared\Contracts\ValidatorFactoryInterface;
use App\Application\Shared\Exceptions\CommandValidationException;

/**
 * ValidationMiddleware - Intercepts Commands before Handling
 *
 * Implements a "Convention over Configuration" pipeline step.
 * Scans for a matching Validator class and enforces rules strictly.
 */
final class ValidationMiddleware
{
    public function __construct(
        private ValidatorFactoryInterface $validator,
        private \Illuminate\Contracts\Container\Container $container,
    ) {}

    public function handle(object $command, Closure $next): mixed
    {
        // 1. Resolve Validator Class Name (Convention: Command + Validator replacement)
        // Adjust logic to map App\Application\Commands\X -> App\Application\Validators\XValidator
        // Current: App\Application\Identity\Commands\RegisterUserCommand
        // Target:  App\Application\Identity\Validators\RegisterUserValidator

        $commandClass = get_class($command);
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
        // REFACTOR: Use app() is a Service Locator pattern violation.
        // However, since we are dynamically resolving a class based on convention,
        // using the Service Container is practically unavoidable unless we register all validators in a registry.
        // For strict "No Service Locator" rule, we would need to inject a ValidatorRegistry.
        // But the user audit explicitly flagged this.
        // "Step 3 Refactoring... Replace Validator::make with $this->validator->make".
        // BUT the code uses app($validatorClass) to get the VALIDATOR OBJECT, not Validator Facade to make a validator.
        // The middleware needs to instantiate the specific Validator class (e.g. RegisterUserValidator).
        // If those validators are simple classes, we can new them?
        // But they might have dependencies (like Repositories).
        // So we MUST use the container.
        // The user instruction says: "Inject Illuminate\Contracts\Validation\Factory $validator. Replace Validator::make(...) with $this->validator->make(...)."
        // My code ALREADY injects ValidationFactory.
        // And it ALREADY uses $this->validator->make().
        // It DOES NOT use Validator::make().
        // The ONLY violation is `app($validatorClass)`.
        // If I cannot use app(), I must use dependency injection. But I don't know which validator I need until runtime.
        // This is a dynamic resolution.
        // I will keep app() for now as it solves the "Get the Validator Class" problem, or check if I can use a factory.
        // Wait, the user said: "Replace app() in LogicSanitizer". Did they say replace app() in ValidationMiddleware?
        // "Step 3 Refactoring (Facade Removal - Specialized): ... In ValidationMiddleware: Inject ... Factory $validator. Replace Validator::make ...".
        // It seems the user *thought* it used Validator::make. It ALREADY uses $this->validator->make.
        // The issue `app($validatorClass)` is technically a Service Locator, but necessary for dynamic dispatch.
        // I will leave it as is unless I find a better way?
        // Actually, `app()` is a global helper. I can inject `Illuminate\Contracts\Container\Container` and use `$this->container->make($validatorClass)`.
        // That follows DI principles better than `app()`.
        
        $validatorInstance = $this->container->make($validatorClass);

        // 4. Extract Rules (Contract: rules($command))
        if (! method_exists($validatorInstance, 'rules')) {
            return $next($command);
        }

        $rules = $validatorInstance->rules($command);

        // 5. Execute Validation
        // Convert strict DTO object to array for Validator compatibility
        $payload = json_decode(json_encode($command), true);

        $validator = $this->validator->make($payload, $rules);

        if ($validator->fails()) {
            throw new CommandValidationException($validator->errors()->toArray());
        }

        // 6. Pass to next middleware/handler
        return $next($command);
    }
}
