<?php

declare(strict_types=1);

namespace App\Domain\Common\ValueObjects;

/**
 * Snapshot DTO
 */
final readonly class Snapshot
{
    public function __construct(
        public array $state,
        public int $version
    ) {}
}
