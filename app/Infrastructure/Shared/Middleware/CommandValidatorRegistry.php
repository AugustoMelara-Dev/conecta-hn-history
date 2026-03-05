<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Middleware;

use App\Application\Shared\Contracts\CommandValidatorRegistryInterface;

final class CommandValidatorRegistry implements CommandValidatorRegistryInterface
{
    /** @var array<class-string, object> */
    private array $map = [];

    public function register(string $commandClass, object $validator): void
    {
        $this->map[$commandClass] = $validator;
    }

    public function findFor(string $commandClass): ?object
    {
        return $this->map[$commandClass] ?? null;
    }
}
