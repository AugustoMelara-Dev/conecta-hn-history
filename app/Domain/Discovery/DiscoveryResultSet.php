<?php

declare(strict_types=1);

namespace App\Domain\Discovery;

final readonly class DiscoveryResultSet
{
    /**
     * @param  list<array<string, mixed>>  $results
     * @param  list<array<string, mixed>>  $comparables
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $results,
        public array $comparables,
        public array $meta,
    ) {}
}
