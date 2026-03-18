<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface IdGeneratorInterface
{
    /**
     * Generate a new unique identifier.
     */
    public function generate(): string;
}
