<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * RepositoryContract - Persistence Layer Abstraction
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Decoupling Business Logic from Eloquent       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  THE PROBLEM WITH DIRECT ELOQUENT USAGE:                                 ║
 * ║  ┌──────────────────┐                                                   ║
 * ║  │ ProductService   │──────────────► Product::where(...)                ║
 * ║  └──────────────────┘                     │                             ║
 * ║                                           ├── Eloquent-specific         ║
 * ║                                           ├── Hard to test (needs DB)   ║
 * ║                                           └── Tenant scope leakage      ║
 * ║                                                                          ║
 * ║  ─────────────────────────────────────────────────────────────────────── ║
 * ║                                                                          ║
 * ║  THE SOLUTION WITH REPOSITORY PATTERN:                                   ║
 * ║  ┌──────────────────┐       ┌───────────────────────┐                   ║
 * ║  │ ProductService   │──────►│  ProductRepository    │ (Contract)        ║
 * ║  └──────────────────┘       └───────────────────────┘                   ║
 * ║                                      ▲                                   ║
 * ║                    ┌─────────────────┼─────────────────┐                ║
 * ║                    │                 │                 │                ║
 * ║          ┌─────────┴──────┐  ┌───────┴───────┐  ┌──────┴──────┐         ║
 * ║          │EloquentProduct │  │ CachedProduct │  │ MockProduct │         ║
 * ║          │   Repository   │  │  Repository   │  │ Repository  │         ║
 * ║          └────────────────┘  └───────────────┘  └─────────────┘         ║
 * ║                                                                          ║
 * ║  TENANT SCOPING GUARANTEE:                                              ║
 * ║    All implementations MUST automatically scope to CurrentTenant.       ║
 * ║    The consuming code NEVER needs to add ->where('tenant_id', ...).     ║
 * ║                                                                          ║
 * ║  CONSTRAINT: Methods return Domain Objects/DTOs, NEVER Query Builders.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 *   class ProductService {
 *       public function __construct(private ProductRepository $products) {}
 *       public function getFeatured(): Collection {
 *           return $this->products->findByCriteria(['is_featured' => true]);
 *       }
 *   }
 *
 * @template TModel
 * @template TId
 */
interface RepositoryContract
{
    /**
     * Find a single entity by its primary key.
     *
     * @param TId $id
     * @return TModel|null
     */
    public function find(mixed $id): mixed;

    /**
     * Find a single entity by ID or throw exception.
     *
     * @param TId $id
     * @return TModel
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(mixed $id): mixed;

    /**
     * Get all entities (use with caution on large datasets).
     *
     * @return Collection<int, TModel>
     */
    public function all(): Collection;

    /**
     * Find entities matching criteria.
     *
     * @param array<string, mixed> $criteria [column => value] pairs
     * @param array<string> $orderBy ['column' => 'asc|desc']
     * @param int|null $limit
     * @return Collection<int, TModel>
     */
    public function findByCriteria(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null
    ): Collection;

    /**
     * Find a single entity by criteria.
     *
     * @param array<string, mixed> $criteria
     * @return TModel|null
     */
    public function findOneByCriteria(array $criteria): mixed;

    /**
     * Paginate entities.
     *
     * @param int $perPage
     * @param array<string, mixed> $criteria Optional filter criteria
     * @param array<string> $orderBy
     * @return LengthAwarePaginator
     */
    public function paginate(
        int $perPage = 15,
        array $criteria = [],
        array $orderBy = []
    ): LengthAwarePaginator;

    /**
     * Create a new entity.
     *
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function create(array $data): mixed;

    /**
     * Update an existing entity.
     *
     * @param TId $id
     * @param array<string, mixed> $data
     * @return TModel
     */
    public function update(mixed $id, array $data): mixed;

    /**
     * Delete an entity.
     *
     * @param TId $id
     * @return bool
     */
    public function delete(mixed $id): bool;

    /**
     * Check if an entity exists by criteria.
     *
     * @param array<string, mixed> $criteria
     * @return bool
     */
    public function exists(array $criteria): bool;

    /**
     * Count entities matching criteria.
     *
     * @param array<string, mixed> $criteria
     * @return int
     */
    public function count(array $criteria = []): int;

    /**
     * Get entities by their IDs.
     *
     * @param array<TId> $ids
     * @return Collection<int, TModel>
     */
    public function findByIds(array $ids): Collection;

    /**
     * Begin a database transaction.
     * (Useful for multi-step operations)
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction.
     */
    public function commit(): void;

    /**
     * Rollback the current transaction.
     */
    public function rollback(): void;
}
