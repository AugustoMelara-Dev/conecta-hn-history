<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * RepositoryContract - Memory-Safe Persistence Layer Abstraction
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL RATIONALE: Big Data Resilience                           ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  PROBLEM: Unbounded retrieval methods cause Out-Of-Memory (OOM) errors  ║
 * ║    $repo->all() on 2 million orders → 2M Eloquent models → OOM crash   ║
 * ║                                                                          ║
 * ║  SOLUTION: Enforce lazy loading at the CONTRACT level.                  ║
 * ║    - REMOVED: all(): Collection (anti-pattern for scalable systems)    ║
 * ║    - ADDED: cursor(): LazyCollection (constant RAM usage)              ║
 * ║                                                                          ║
 * ║  WHY LazyCollection?                                                    ║
 * ║    - Uses PHP Generators internally                                     ║
 * ║    - Only 1 model in memory at a time                                  ║
 * ║    - Database cursor stays open, fetching rows on-demand               ║
 * ║    - 2 million rows = same RAM as 100 rows                             ║
 * ║                                                                          ║
 * ║  TENANT SCOPING:                                                        ║
 * ║    All implementations MUST automatically scope to CurrentTenant.       ║
 * ║                                                                          ║
 * ║  CONSTRAINT: Methods return Domain Objects/DTOs, NEVER Query Builders.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
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
     * Stream ALL entities using a database cursor.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  @notice MEMORY SAFETY: This method uses LazyCollection (Generators)  ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  WHY NOT return Collection?                                            ║
     * ║    Collection loads EVERYTHING into RAM before returning.              ║
     * ║    2 million rows × 5KB/model = 10GB RAM = INSTANT CRASH              ║
     * ║                                                                        ║
     * ║  LazyCollection uses database cursors:                                ║
     * ║    - Only 1 row fetched from DB at a time                             ║
     * ║    - Process → GC → Next row                                          ║
     * ║    - RAM stays flat at ~10MB regardless of table size                 ║
     * ║                                                                        ║
     * ║  USAGE:                                                               ║
     * ║    foreach ($repo->cursor() as $model) {                             ║
     * ║        process($model); // Only 1 model in memory                    ║
     * ║    }                                                                  ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     *
     * @return LazyCollection<int, TModel>
     */
    public function cursor(): LazyCollection;

    /**
     * Find entities matching criteria.
     *
     * @notice For large result sets, use cursorByCriteria() instead.
     *
     * @param array<string, mixed> $criteria [column => value] pairs
     * @param array<string, string> $orderBy ['column' => 'asc|desc']
     * @param int|null $limit REQUIRED for unbounded queries (prevents OOM)
     * @return Collection<int, TModel>
     */
    public function findByCriteria(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null
    ): Collection;

    /**
     * Stream entities matching criteria using database cursor.
     *
     * @notice Use this instead of findByCriteria() when result set may be large.
     *
     * @param array<string, mixed> $criteria [column => value] pairs
     * @param array<string, string> $orderBy ['column' => 'asc|desc']
     * @return LazyCollection<int, TModel>
     */
    public function cursorByCriteria(
        array $criteria = [],
        array $orderBy = []
    ): LazyCollection;

    /**
     * Find a single entity by criteria.
     *
     * @param array<string, mixed> $criteria
     * @return TModel|null
     */
    public function findOneByCriteria(array $criteria): mixed;

    /**
     * Paginate entities (memory-safe by design due to LIMIT).
     *
     * @param int $perPage
     * @param array<string, mixed> $criteria Optional filter criteria
     * @param array<string, string> $orderBy
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
     * Check if an entity exists by criteria (uses COUNT, no model loading).
     *
     * @param array<string, mixed> $criteria
     * @return bool
     */
    public function exists(array $criteria): bool;

    /**
     * Count entities matching criteria (uses COUNT, no model loading).
     *
     * @param array<string, mixed> $criteria
     * @return int
     */
    public function count(array $criteria = []): int;

    /**
     * Get entities by their IDs.
     *
     * @notice Only use when you KNOW the ID list is bounded (e.g., cart items).
     *         For unbounded ID lists, use cursorByIds().
     *
     * @param array<TId> $ids
     * @return Collection<int, TModel>
     */
    public function findByIds(array $ids): Collection;

    /**
     * Stream entities by IDs using cursor.
     *
     * @param array<TId> $ids
     * @return LazyCollection<int, TModel>
     */
    public function cursorByIds(array $ids): LazyCollection;

    /**
     * Begin a database transaction.
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
