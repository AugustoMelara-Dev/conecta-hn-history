<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus;

use App\Application\Shared\Contracts\CommandBusInterface;
use Illuminate\Bus\Dispatcher;

/**
 * IlluminateCommandBus
 *
 * ADAPTER: Implementation of the CommandBus Port using Laravel's native Bus.
 */
final class IlluminateCommandBus implements CommandBusInterface
{
    public function __construct(
        private readonly Dispatcher $dispatcher
    ) {}

    /**
     * {@inheritDoc}
     */
    public function dispatch(object $command): mixed
    {
        // dispatchSync ensures the command runs in the current process (Synchronous)
        // This is crucial for returning immediate feedback/errors to the API.
        return $this->dispatcher->dispatchSync($command);
    }
}
