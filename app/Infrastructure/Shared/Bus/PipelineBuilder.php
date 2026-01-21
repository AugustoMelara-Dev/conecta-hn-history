<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Bus;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * PipelineBuilder
 *
 * Fluent Factory for assembling "Russian Doll" Decorator Stacks.
 *
 * Usage:
 * PipelineBuilder::make(CoreHandler::class)
 *    ->through([
 *        ValidatorDecorator::class,    // Outer (Runs First)
 *        IdempotencyDecorator::class,  // Middle
 *        TransactionDecorator::class,  // Inner (Runs Last before Core)
 *    ])
 *    ->build($container);
 */
final class PipelineBuilder
{
    private string $coreHandlerClass;
    private array $decorators = [];

    private function __construct(string $coreHandlerClass)
    {
        $this->coreHandlerClass = $coreHandlerClass;
    }

    public static function make(string $coreHandlerClass): self
    {
        return new self($coreHandlerClass);
    }

    public function through(array $decorators): self
    {
        $this->decorators = $decorators;
        return $this;
    }

    public function build(Container $container): object
    {
        // 1. Resolve the Core Handler (The innermost doll)
        $stack = $container->make($this->coreHandlerClass);

        // 2. Wrap it with decorators in REVERSE order
        // We iterate effectively from INNER -> OUTER
        // So the last item in the array becomes the structure wrapping the previous stack.
        // Wait, "through([A, B, C])" means A is outer, C is inner?
        // Usually middleware: [A, B, C] -> A calls B calls C calls Core.
        // So A wraps B wrapping C wrapping Core.
        // To build this:
        // Core
        // C(Core)
        // B(C)
        // A(B)
        // So we invoke constructors in reverse order of the array. C, then B, then A.
        
        $reversedDecorators = array_reverse($this->decorators);

        foreach ($reversedDecorators as $decoratorClass) {
            // Check if decorator class exists
            if (!class_exists($decoratorClass)) {
                throw new InvalidArgumentException("Decorator class not found: {$decoratorClass}");
            }

            // Decorators usually expect the 'next' handler/interface as a dependency.
            // We assume a convention where the inner handler is injected.
            // Some might use 'inner', 'next', or the interface type hint.
            // standard Laravel injection will try to resolve constructor params.
            // We need to explicitly provide the *instance* of the wrapped handler.
            // Typically this parameter is named $inner or $next.
            
            // We can try to use standard binding, but since we are building a chain of the SAME interface,
            // type-hinting alone is ambiguous for the container (it would resolve the binding, causing infinite loop).
            // We MUST inject this specific instance.
            
            // Let's assume common parameter names: 'inner', 'next', 'handler', 'service'.
            // To be safe and loose, we can try to pass it as a contextual binding or construct manually if simple.
            // BUT, decorators might have other dependencies (Logger, connection, etc).
            // So $container->makeWith() is best.
            // We need to know the parameter name found in the decorator's constructor?
            // Or we just rely on naming convention 'inner' or 'next'.
            
            // The prompt says: "Use container->make($decoratorClass, ['next' => $stack])"
            // It explicitly suggests 'next' as the parameter name.
            
            $stack = $container->make($decoratorClass, [
                'next' => $stack,   // Convention 1
                'inner' => $stack,  // Convention 2 (common in this project based on previous snippets)
                'handler' => $stack, // Convention 3
                // We pass multiple keys to cover bases, Laravel ignores extra params in makeWith
            ]);
        }

        return $stack;
    }
}
