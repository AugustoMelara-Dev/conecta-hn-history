<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

interface PaginatedResult
{
    /** @return array<int, mixed> */
    public function items(): array;

    public function total(): int;

    public function perPage(): int;

    public function currentPage(): int;

    public function lastPage(): int;
}
