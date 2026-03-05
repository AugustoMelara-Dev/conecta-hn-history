<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface CommandValidatorRegistryInterface
{
    /**
     * Returns the validator instance for a given command class, or null if none registered.
     *
     * @param  class-string  $commandClass
     */
    public function findFor(string $commandClass): ?object;
}
