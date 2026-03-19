<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockStatus;
use App\Events\Inventory\LowStockThresholdReached;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * InventoryManager - Domain Service for Stock Validation & Mutation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SINGLE RESPONSIBILITY: All inventory logic isolated from controller    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * RESPONSIBILITIES:
 * - Pessimistic locking (lockForUpdate)
 * - Soft-delete enforcement (whereNull)
 * - Stock validation (manage_stock, stock_quantity)
 * - Atomic decrement with state transitions
 * - Low stock event dispatching
 *
 * FORWARD COMPATIBILITY:
 * This abstraction layer allows future swap to "Variant Inventory"
 * without breaking controller code.
 */
class InventoryManager implements \App\Application\Shared\Contracts\InventoryManagerInterface, \App\Contracts\InventoryManagerInterface
{
    /**
     * Validate and lock product inventory for order creation.
     *
     * Uses canonical locking order (sorted IDs) to prevent deadlocks.
     * Returns a keyed collection of locked products ready for pricing.
     *
     * ⚠️ PRECONDITION: This method acquires pessimistic locks (lockForUpdate).
     * The caller MUST wrap this call within a DB::transaction() closure to
     * ensure locks are held for the duration of the atomic unit of work.
     * Calling this outside a transaction will release locks immediately
     * after this method returns, creating a race condition vulnerability.
     *
     * @param  array  $cartItems  Cart items with product_id and quantity
     * @param  int  $tenantId  Tenant context for isolation
     * @return Collection<int, Product> Products keyed by ID
     *
     * @throws ValidationException If product unavailable or deleted
     */
    public function validateAndLock(array $cartItems, int|string $tenantId): Collection
    {
        // ═══════════════════════════════════════════════════════════════════════
        // STEP 1: Extract and sort product IDs (canonical lock order)
        // ═══════════════════════════════════════════════════════════════════════
        $productIds = collect($cartItems)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->sort()  // Canonical ordering prevents deadlocks
            ->values()
            ->toArray();

        if (empty($productIds)) {
            return collect();
        }

        // Build quantity map per product
        $quantityMap = collect($cartItems)
            ->filter(fn ($item) => ! empty($item['product_id']))
            ->groupBy('product_id')
            ->map(fn ($items) => $items->sum('quantity'))
            ->toArray();

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 2: Acquire exclusive locks in sorted order
        // ═══════════════════════════════════════════════════════════════════════
        $rawProducts = DB::table('products')
            ->whereIn('id', $productIds)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')  // SECURITY: Block ghost purchases
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $lockedProducts = Product::hydrate($rawProducts->toArray())->keyBy('id');

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 3: Validate stock and perform atomic decrements
        // ═══════════════════════════════════════════════════════════════════════
        foreach ($quantityMap as $productId => $totalRequested) {
            if (! isset($lockedProducts[$productId])) {
                // Product not found or soft-deleted
                throw ValidationException::withMessages([
                    'items' => "Producto ID {$productId} no encontrado o no disponible.",
                ]);
            }

            $product = $lockedProducts[$productId];

            if ($product->manage_stock) {
                // BRANCH A: Managed/Finite Stock
                $this->validateAndDecrementManagedStock($product, $totalRequested);
            } else {
                // BRANCH B: Unmanaged/Infinite Stock
                $this->validateUnmanagedStock($product);
            }
        }

        return $lockedProducts;
    }

    /**
     * Validate and decrement managed stock (finite inventory).
     */
    protected function validateAndDecrementManagedStock(Product $product, int $quantity): void
    {
        $currentStock = (int) ($product->stock_quantity ?? 0);

        if ($currentStock < $quantity) {
            throw ValidationException::withMessages([
                'items' => "Stock insuficiente para '{$product->name}'. Disponible: {$currentStock}, Solicitado: {$quantity}",
            ]);
        }

        // Atomic decrement
        $product->decrement('stock_quantity', $quantity);
        $newStock = $currentStock - $quantity;

        // State transition: mark out_of_stock if depleted
        $allowsBackorders = $product->allow_backorders ?? false;
        if ($newStock <= 0 && ! $allowsBackorders) {
            $product->update(['stock_status' => StockStatus::OutOfStock]);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // TRANSACTIONAL EVENT DISPATCH: Prevent phantom alerts
        // ═══════════════════════════════════════════════════════════════════════
        // Wrap in DB::afterCommit to ensure event only fires if transaction
        // succeeds. If rollback occurs, the closure is discarded, preventing:
        // - Phantom notifications to store owners
        // - Rate limiter cache keys being set incorrectly
        $threshold = $product->low_stock_threshold;
        if ($threshold !== null && $newStock <= $threshold && $newStock > 0) {
            // Capture values for closure (product may change after this point)
            $productId = $product->id;
            $finalNewStock = $newStock;
            $finalThreshold = $threshold;

            DB::afterCommit(function () use ($productId, $finalNewStock, $finalThreshold) {
                // Re-fetch fresh product instance for event
                $freshProduct = Product::find($productId);
                if ($freshProduct) {
                    LowStockThresholdReached::dispatch($freshProduct, $finalNewStock, $finalThreshold);
                }
            });
        }
    }

    /**
     * Validate unmanaged stock (services, digital goods).
     */
    protected function validateUnmanagedStock(Product $product): void
    {
        if ($product->stock_status !== \App\Enums\StockStatus::InStock) {
            throw ValidationException::withMessages([
                'items' => "Producto '{$product->name}' no disponible.",
            ]);
        }
    }

    /**
     * Restock inventory from a cancelled/deleted order (Reverse Logistics).
     *
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  INVENTORY RESTORATION: Reverse the stock consumption from an order     ║
     * ║  PATCHED 2026-02-06: Added lockForUpdate() to prevent TOCTOU race       ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     *
     * RESPONSIBILITIES:
     * - Restore stock_quantity for each managed product
     * - Transition stock_status back to 'in_stock' if applicable
     * - Handle products that may have been deleted since order creation
     *
     * CONCURRENCY SAFETY:
     * - DB::transaction ensures ACID compliance
     * - lockForUpdate() prevents "Lost Update" race condition
     * - Canonical ordering (sorted IDs) prevents deadlocks
     *
     * @param  \App\Models\Order  $order  The order to restore inventory from
     */
    public function restock(\App\Models\Order $order): void
    {
        // Load order items if not already loaded
        $order->loadMissing('items');

        // VC-AUDIT-102: Prevent scope bypass if tenant_id is missing
        if ($order->tenant_id === null) {
            throw new \LogicException('Cannot restock inventory: Order tenant_id is missing.');
        }

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: EXTRACTION - Extract unique product IDs with canonical ordering
        // ═══════════════════════════════════════════════════════════════════════
        $productIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->sort()  // Canonical ordering prevents deadlocks
            ->values()
            ->toArray();

        if (empty($productIds)) {
            return; // No products to restock
        }

        // Build quantity map per product (aggregate same-product items)
        $quantityMap = $order->items
            ->filter(fn ($item) => ! empty($item->product_id))
            ->groupBy('product_id')
            ->map(fn ($items) => $items->sum('quantity'))
            ->toArray();

        // Telemetry buffer (RAM-only, zero disk I/O during locks)
        $telemetryBuffer = [];

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: TRANSACTIONAL BATCH PROCESSING WITH PESSIMISTIC LOCKING
        // ═══════════════════════════════════════════════════════════════════════
        DB::transaction(function () use ($productIds, $quantityMap, $order, &$telemetryBuffer) {

            // ═══════════════════════════════════════════════════════════════════
            // SINGLE BATCH QUERY with PESSIMISTIC LOCK + TENANT ISOLATION
            // ═══════════════════════════════════════════════════════════════════
            // lockForUpdate() creates SELECT ... FOR UPDATE
            // Holds exclusive row locks until transaction commits
            //
            // CRITICAL: Use Eloquent query builder (not DB::table) to enable
            // proper enum casting. DB::table + hydrate fails with backed enums.

            // VC-AUDIT-102: Prevent scope bypass if tenant_id is missing
            $lockedProducts = Product::query()
                ->withoutGlobalScopes() // Bypass visibility scopes for admin operation
                ->whereIn('id', $productIds)
                ->where('tenant_id', $order->tenant_id)
                ->whereNull('deleted_at')
                ->orderBy('id')  // Canonical order prevents deadlocks
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // ═══════════════════════════════════════════════════════════════════
            // PHASE 3: IN-MEMORY COMPUTATION & ATOMIC WRITE MERGING
            // ═══════════════════════════════════════════════════════════════════
            foreach ($quantityMap as $productId => $restoreQty) {
                // O(1) retrieval from in-memory map
                $product = $lockedProducts->get($productId);

                if (! $product) {
                    continue; // Product was hard-deleted, skip gracefully
                }

                // Skip unmanaged stock products
                if (! $product->manage_stock) {
                    continue;
                }

                // ═══════════════════════════════════════════════════════════════
                // ATOMIC STATE COMPUTATION (under lock - no race possible)
                // ═══════════════════════════════════════════════════════════════
                $currentStock = (int) ($product->stock_quantity ?? 0);
                $newStock = $currentStock + $restoreQty;

                // ═══════════════════════════════════════════════════════════════
                // ATOMIC WRITE MERGING: Single UPDATE per product
                // ═══════════════════════════════════════════════════════════════
                $updatePayload = ['stock_quantity' => $newStock];

                // State recovery: If product was out_of_stock, restore to in_stock
                if ($product->stock_status === StockStatus::OutOfStock && $newStock > 0) {
                    $updatePayload['stock_status'] = StockStatus::InStock;
                }

                $product->update($updatePayload);

                // ═══════════════════════════════════════════════════════════════
                // BUFFER TELEMETRY (Zero disk I/O during lock)
                // ═══════════════════════════════════════════════════════════════
                $telemetryBuffer[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_restored' => $restoreQty,
                    'previous_stock' => $currentStock,
                    'new_stock' => $newStock,
                    'order_id' => $order->id,
                ];
            }

            // ═══════════════════════════════════════════════════════════════════
            // TRANSACTIONAL SCOPE: afterCommit INSIDE transaction
            // ═══════════════════════════════════════════════════════════════════
            DB::afterCommit(function () use ($telemetryBuffer) {
                foreach ($telemetryBuffer as $logEntry) {
                    \Illuminate\Support\Facades\Log::debug('Inventory restored from cancelled order', $logEntry);
                }
            });
        });
    }

    /**
     * Consume inventory for a restored order (Reverse of restock).
     *
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  HIGH-PERFORMANCE BATCH PROCESSING WITH PESSIMISTIC LOCKING             ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     *
     * STRATEGY: "Load-Map-Process" (O(1) Query Performance)
     * 1. Extract ALL product IDs from order items (single pass)
     * 2. Execute ONE batch query with lockForUpdate() (prevents race conditions)
     * 3. Build in-memory map for O(1) lookup
     * 4. Process items using the map (no additional queries)
     *
     * CONCURRENCY SAFETY:
     * - DB::transaction ensures ACID compliance
     * - lockForUpdate() prevents "Check-Then-Act" race condition
     * - ValidationException triggers automatic ROLLBACK
     *
     * @param  \App\Models\Order  $order  The order being restored
     *
     * @throws ValidationException If insufficient stock (triggers rollback)
     */
    public function consume(\App\Models\Order $order): void
    {
        $order->loadMissing('items');

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 1: EXTRACTION - Extract unique product IDs
        // ═══════════════════════════════════════════════════════════════════════
        $productIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->sort()  // Canonical ordering prevents deadlocks
            ->values()
            ->toArray();

        if (empty($productIds)) {
            return; // No products to consume
        }

        // Build quantity map per product (aggregate same-product items)
        $quantityMap = $order->items
            ->filter(fn ($item) => ! empty($item->product_id))
            ->groupBy('product_id')
            ->map(fn ($items) => $items->sum('quantity'))
            ->toArray();

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 2: TRANSACTIONAL BATCH PROCESSING (Zero-Waste + Domain Events)
        // ═══════════════════════════════════════════════════════════════════════
        // Entire operation wrapped in transaction for ACID compliance.
        // Optimizations:
        // - Write Merging: ONE update() per product
        // - Dual Buffer Strategy: Separate telemetry and domain events
        // - Transactional Scoping: afterCommit bound to THIS transaction

        // DUAL BUFFER INITIALIZATION
        // - $telemetryBuffer: Collects log data (observability)
        // - $eventBuffer: Collects domain events (low stock alerts)
        // Both are RAM-only, zero disk I/O during active locks
        $telemetryBuffer = [];
        $eventBuffer = [];

        DB::transaction(function () use ($productIds, $quantityMap, $order, &$telemetryBuffer, &$eventBuffer) {

            // ═══════════════════════════════════════════════════════════════
            // SINGLE BATCH QUERY with PESSIMISTIC LOCK + TENANT ISOLATION
            // ═══════════════════════════════════════════════════════════════
            // lockForUpdate() holds exclusive row locks until transaction ends.
            // VC-AUDIT-104: Use Eloquent to ensure Enum casting (not DB::table+hydrate)
            $lockedProducts = Product::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $productIds)
                ->where('tenant_id', $order->tenant_id)
                ->whereNull('deleted_at')
                ->orderBy('id')  // Canonical order prevents deadlocks
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // ═══════════════════════════════════════════════════════════════
            // PHASE 3: IN-MEMORY COMPUTATION & ATOMIC WRITE MERGING
            // ═══════════════════════════════════════════════════════════════
            foreach ($quantityMap as $productId => $requiredQty) {
                // O(1) retrieval from in-memory map
                $product = $lockedProducts->get($productId);

                if (! $product) {
                    continue; // Product was hard-deleted, skip gracefully
                }

                // Skip unmanaged stock products
                if (! $product->manage_stock) {
                    continue;
                }

                $currentStock = (int) ($product->stock_quantity ?? 0);

                // ═══════════════════════════════════════════════════════════
                // STRICT AVAILABILITY GUARD
                // ═══════════════════════════════════════════════════════════
                if ($currentStock < $requiredQty) {
                    throw ValidationException::withMessages([
                        'order' => "No se puede restaurar la orden: Stock insuficiente para '{$product->name}'. Disponible: {$currentStock}, Requerido: {$requiredQty}",
                    ]);
                }

                // ═══════════════════════════════════════════════════════════
                // IN-MEMORY STATE COMPUTATION
                // ═══════════════════════════════════════════════════════════
                $newStock = $currentStock - $requiredQty;

                // ═══════════════════════════════════════════════════════════
                // ATOMIC WRITE MERGING: Single UPDATE per product
                // ═══════════════════════════════════════════════════════════
                $updatePayload = ['stock_quantity' => $newStock];

                $allowsBackorders = $product->allow_backorders ?? false;
                if ($newStock <= 0 && ! $allowsBackorders) {
                    $updatePayload['stock_status'] = StockStatus::OutOfStock;
                }

                $product->update($updatePayload);

                // ═══════════════════════════════════════════════════════════
                // LOW STOCK THRESHOLD DETECTION (Event Buffer)
                // ═══════════════════════════════════════════════════════════
                // Capture low stock events WITHOUT dispatching during lock.
                // Conditions:
                // 1. Threshold is defined (not null)
                // 2. New stock is at or below threshold
                // 3. New stock is > 0 (distinguish from "out of stock")
                $threshold = $product->low_stock_threshold;
                if ($threshold !== null && $newStock <= $threshold && $newStock > 0) {
                    // Lightweight serialization: IDs only, no model instances
                    // This prevents memory bloat and serialization issues
                    $eventBuffer[] = [
                        'product_id' => $product->id,
                        'new_stock' => $newStock,
                        'threshold' => $threshold,
                    ];
                }

                // ═══════════════════════════════════════════════════════════
                // BUFFER TELEMETRY (Zero disk I/O)
                // ═══════════════════════════════════════════════════════════
                $telemetryBuffer[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_consumed' => $requiredQty,
                    'new_stock' => $newStock,
                    'order_id' => $order->id,
                ];
            }

            // ═══════════════════════════════════════════════════════════════
            // TRANSACTIONAL SCOPE: afterCommit INSIDE transaction
            // ═══════════════════════════════════════════════════════════════
            // This ensures the callback is bound to THIS specific transaction.
            // In nested transaction scenarios, this prevents premature firing.
            // Callback executes AFTER:
            // 1. Transaction committed successfully
            // 2. All row locks released
            // 3. Other transactions can proceed

            DB::afterCommit(function () use ($telemetryBuffer, $eventBuffer) {
                // ═══════════════════════════════════════════════════════════
                // FLUSH DOMAIN EVENTS (Low Stock Alerts)
                // ═══════════════════════════════════════════════════════════
                // Re-hydrate fresh product instances for Event Listeners.
                // This decouples Event system from the locked transaction.
                foreach ($eventBuffer as $eventData) {
                    $freshProduct = Product::find($eventData['product_id']);
                    if ($freshProduct) {
                        LowStockThresholdReached::dispatch(
                            $freshProduct,
                            $eventData['new_stock'],
                            $eventData['threshold']
                        );
                    }
                }

                // ═══════════════════════════════════════════════════════════
                // FLUSH TELEMETRY (Observability Logs)
                // ═══════════════════════════════════════════════════════════
                foreach ($telemetryBuffer as $logEntry) {
                    \Illuminate\Support\Facades\Log::debug('Inventory consumed for restored order', $logEntry);
                }
            });
        });
    }
}
