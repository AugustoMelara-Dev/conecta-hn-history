<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * CommandBus Interface (Hexagonal Port)
 *
 * Defines the contract for dispatching commands.
 * Decouples Controllers from the concrete Bus implementation (Laravel, Tactician, etc).
 */
interface CommandBusInterface
{
    /**
     * Dispatch a command to its handler.
     */
    public function dispatch(object $command): mixed;
}
