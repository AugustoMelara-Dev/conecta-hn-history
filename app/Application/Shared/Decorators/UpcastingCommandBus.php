<?php

declare(strict_types=1);

namespace App\Application\Shared\Decorators;

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\CommandUpcasterInterface;

/**
 * UpcastingCommandBus - Schema Evolution Decorator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DECORATOR PATTERN: Intercepts commands before TransactionalCommandBus ║
 * ║  and transforms payloads from older schema versions to current shape.  ║
 * ║  TENANT SAFE: Operates only on DTO properties, never touches DB/scope.║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpcastingCommandBus implements CommandBusInterface
{
    /**
     * @param  CommandBusInterface  $next  The inner CommandBus (e.g., TransactionalCommandBus)
     * @param  iterable<CommandUpcasterInterface>  $upcasters  Tagged upcasters resolved from container
     */
    public function __construct(
        private CommandBusInterface $next,
        private iterable $upcasters,
    ) {}

    public function dispatch(object $command): mixed
    {
        $currentCommand = $command;

        foreach ($this->upcasters as $upcaster) {
            if ($upcaster->supports($currentCommand)) {
                $currentCommand = $upcaster->upcast($currentCommand);
            }
        }

        return $this->next->dispatch($currentCommand);
    }
}
