<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

final readonly class RegistryMutationFinding
{
    public function __construct(
        public string $relativeFile,
        public string $table,
        public string $method,
        public int $line,
    ) {}
}
