<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

interface PaginatedResult
{
    /** @return array<int, mixed> */
    public function items(): array;

    /**
     * Apply a transformation to each paginated item while preserving metadata.
     */
    public function through(callable $callback): self;

    public function total(): int;

    public function perPage(): int;

    public function currentPage(): int;

    public function lastPage(): int;
}
