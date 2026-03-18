<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Domain\Shared\Contracts\PaginatedResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * EloquentPaginatedResult - Infrastructure adapter that bridges
 * Laravel's LengthAwarePaginator to the domain PaginatedResult contract.
 *
 * Allowed in Infrastructure (not Domain) — this is the Anti-Corruption Layer.
 */
final class EloquentPaginatedResult implements \JsonSerializable, PaginatedResult
{
    public function __construct(private readonly LengthAwarePaginator $paginator) {}

    public function jsonSerialize(): mixed
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items(),
            'last_page' => $this->lastPage(),
            'per_page' => $this->perPage(),
            'total' => $this->total(),
        ];
    }

    /** @return array<int, mixed> */
    public function items(): array
    {
        return $this->paginator->items();
    }

    public function total(): int
    {
        return $this->paginator->total();
    }

    public function perPage(): int
    {
        return $this->paginator->perPage();
    }

    public function currentPage(): int
    {
        return $this->paginator->currentPage();
    }

    public function lastPage(): int
    {
        return $this->paginator->lastPage();
    }

    public function through(callable $callback): self
    {
        $items = $this->paginator
            ->getCollection()
            ->map($callback);

        $this->paginator->setCollection(new Collection($items->all()));

        return $this;
    }
}
