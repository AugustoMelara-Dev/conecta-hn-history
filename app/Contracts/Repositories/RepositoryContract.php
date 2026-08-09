<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * RepositoryContract - Production-Grade Persistence Layer Abstraction
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL PRINCIPLES                                                ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  1. SINGLE RESPONSIBILITY (SRP):                                         ║
 * ║     This repository handles PERSISTENCE only - no transaction control.  ║
 * ║     Transactions belong to the Service Layer (DB::transaction) or a     ║
 * ║     UnitOfWork pattern. Repositories don't orchestrate multi-repo ops.  ║
 * ║                                                                          ║
 * ║  2. MEMORY SAFETY:                                                       ║
 * ║     NO unbounded methods (all() is BANNED). Use cursor() or paginate(). ║
 * ║     LazyCollection uses PHP Generators = constant RAM usage.            ║
 * ║                                                                          ║
 * ║  3. BATCH PERFORMANCE:                                                   ║
 * ║     insert() and upsert() enable single-query bulk operations.          ║
 * ║     5000 products from Excel = 1 query, not 5000 queries.              ║
 * ║                                                                          ║
 * ║  4. TENANT SCOPING (Implicit):                                          ║
 * ║     All implementations MUST automatically scope to CurrentTenant.       ║
 * ║     Consuming code should NEVER manually add tenant_id filters.         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * TRANSACTION MANAGEMENT (Delegated to Service Layer):
 *   class OrderService {
 *       public function place(Cart $cart): Order {
 *           return DB::transaction(function () use ($cart) {
 *               $order = $this->orders->create([...]);
 *               $this->inventory->decrementStock($cart->items);
 *               return $order;
 *           });
 *       }
 *   }
 *
 * @template TModel
 * @template TId
 */
interface RepositoryContract
{
    // ═══════════════════════════════════════════════════════════════════════
    // SINGLE RECORD RETRIEVAL
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Find a single entity by its primary key.
     *
     * @param  TId  $id
     * @return TModel|null
     */
    public function find(mixed $id): mixed;

    /**
     * Find a single entity by ID or throw exception.
     *
     * @param  TId  $id
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(mixed $id): mixed;

    /**
     * Find a single entity by criteria.
     *
     * @param  array<string, mixed>  $criteria
     * @return TModel|null
     */
    public function findOneByCriteria(array $criteria): mixed;

    // ═══════════════════════════════════════════════════════════════════════
    // MEMORY-SAFE BULK RETRIEVAL (LazyCollection = Constant RAM)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Stream ALL entities using a database cursor.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  @notice MEMORY SAFETY: Returns LazyCollection (PHP Generator)        ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  This method keeps RAM usage CONSTANT regardless of table size.       ║
     * ║                                                                        ║
     * ║  HOW IT WORKS:                                                        ║
     * ║    - Opens a database cursor (not SELECT *)                           ║
     * ║    - Fetches 1 row at a time from MySQL/PostgreSQL                   ║
     * ║    - Yields model, then allows PHP GC to free memory                 ║
     * ║    - 2 million rows = same RAM as 100 rows                           ║
     * ║                                                                        ║
     * ║  WHEN TO USE:                                                         ║
     * ║    - Data exports, reports, migrations, background jobs              ║
     * ║    - Any operation that touches "all" records                        ║
     * ║                                                                        ║
     * ║  CAUTION:                                                             ║
     * ║    - Cannot be serialized (don't pass to queue jobs directly)        ║
     * ║    - Keeps DB connection open until fully iterated                   ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     *
     * @return LazyCollection<int, TModel>
     */
    public function cursor(): LazyCollection;

    /**
     * Stream entities matching criteria using database cursor.
     *
     * @param  array<string, mixed>  $criteria  [column => value] pairs
     * @param  array<string, string>  $orderBy  ['column' => 'asc|desc']
     * @return LazyCollection<int, TModel>
     */
    public function cursorByCriteria(
        array $criteria = [],
        array $orderBy = []
    ): LazyCollection;

    /**
     * Find entities matching criteria (for bounded result sets).
     *
     * @notice For large/unbounded results, use cursorByCriteria() instead.
     *
     * @param  array<string, mixed>  $criteria  [column => value] pairs
     * @param  array<string, string>  $orderBy  ['column' => 'asc|desc']
     * @param  int|null  $limit  STRONGLY RECOMMENDED for unbounded queries
     * @return Collection<int, TModel>
     */
    public function findByCriteria(
        array $criteria = [],
        array $orderBy = [],
        ?int $limit = null
    ): Collection;

    /**
     * Get entities by their IDs (for bounded ID lists only).
     *
     * @notice Only use when ID count is bounded (e.g., cart items max 50).
     *
     * @param  array<TId>  $ids
     * @return Collection<int, TModel>
     */
    public function findByIds(array $ids): Collection;

    /**
     * Paginate entities (memory-safe by design due to LIMIT).
     *
     * @param  array<string, mixed>  $criteria  Optional filter criteria
     * @param  array<string, string>  $orderBy
     */
    public function paginate(
        int $perPage = 15,
        array $criteria = [],
        array $orderBy = []
    ): LengthAwarePaginator;

    // ═══════════════════════════════════════════════════════════════════════
    // SINGLE RECORD MUTATIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create a new entity.
     *
     * @param  array<string, mixed>  $data
     * @return TModel The created entity
     */
    public function create(array $data): mixed;

    /**
     * Update an existing entity.
     *
     * @param  TId  $id
     * @param  array<string, mixed>  $data
     * @return TModel The updated entity
     */
    public function update(mixed $id, array $data): mixed;

    /**
     * Delete an entity.
     *
     * @param  TId  $id
     */
    public function delete(mixed $id): bool;

    // ═══════════════════════════════════════════════════════════════════════
    // BATCH OPERATIONS (Critical for Import/Export Performance)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Insert multiple records in a single query.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  @notice PERFORMANCE: Executes ONE INSERT query, not N queries.       ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  5000 products from Excel:                                            ║
     * ║    - create() loop: 5000 queries × 5ms = 25 seconds                  ║
     * ║    - insert(): 1 query × 50ms = 0.05 seconds (500x faster)           ║
     * ║                                                                        ║
     * ║  LIMITATIONS:                                                         ║
     * ║    - Does NOT fire Eloquent events (creating, created)               ║
     * ║    - Does NOT auto-fill timestamps (add manually)                    ║
     * ║    - Does NOT return inserted models (returns bool)                  ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     *
     * @param  array<int, array<string, mixed>>  $rows  Array of row data
     * @return bool Whether the insert succeeded
     */
    public function insert(array $rows): bool;

    /**
     * Insert or update records based on unique key.
     *
     * ╔════════════════════════════════════════════════════════════════════════╗
     * ║  @notice UPSERT: Insert if new, update if exists (single query).      ║
     * ╠════════════════════════════════════════════════════════════════════════╣
     * ║  USAGE: Sync external data (e.g., import products by SKU)            ║
     * ║                                                                        ║
     * ║  $repo->upsert(                                                       ║
     * ║      $productsFromApi,           // Rows to insert/update            ║
     * ║      ['sku'],                    // Unique key(s)                    ║
     * ║      ['name', 'price', 'stock']  // Columns to update if exists      ║
     * ║  );                                                                   ║
     * ╚════════════════════════════════════════════════════════════════════════╝
     *
     * @param  array<int, array<string, mixed>>  $rows  Data to insert/update
     * @param  array<string>|string  $uniqueBy  Unique column(s) for matching
     * @param  array<string>|null  $update  Columns to update (null = all except uniqueBy)
     * @return int Number of affected rows
     */
    public function upsert(array $rows, array|string $uniqueBy, ?array $update = null): int;

    /**
     * Delete multiple entities by criteria (single query).
     *
     * @param  array<string, mixed>  $criteria
     * @return int Number of deleted rows
     */
    public function deleteByCriteria(array $criteria): int;

    /**
     * Update multiple entities by criteria (single query).
     *
     * @param  array<string, mixed>  $criteria  Filter criteria
     * @param  array<string, mixed>  $data  Data to update
     * @return int Number of affected rows
     */
    public function updateByCriteria(array $criteria, array $data): int;

    // ═══════════════════════════════════════════════════════════════════════
    // AGGREGATE OPERATIONS (No Model Loading)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Check if an entity exists by criteria (uses EXISTS, no model loading).
     *
     * @param  array<string, mixed>  $criteria
     */
    public function exists(array $criteria): bool;

    /**
     * Count entities matching criteria (uses COUNT, no model loading).
     *
     * @param  array<string, mixed>  $criteria
     */
    public function count(array $criteria = []): int;
}
