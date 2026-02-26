<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * CommandUpcasterInterface - Schema Evolution Port
 *
 * Inspects a command's schemaVersion and mutates its payload to match
 * the current expected shape before it reaches the handler.
 */
interface CommandUpcasterInterface
{
    /**
     * Determine if this upcaster can handle the given command.
     */
    public function supports(object $command): bool;

    /**
     * Transform the command from an older schema version to the current one.
     */
    public function upcast(object $command): object;
}
