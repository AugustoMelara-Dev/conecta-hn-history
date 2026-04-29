<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Services\Inventory\InventoryEventBuffer;
use App\Services\Inventory\InventoryProductLocker;
use App\Services\Inventory\StockMutationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates inventory locking and mutations while preserving the public contract
 * used by checkout, order observers, and legacy inventory integrations.
 */
class InventoryManager implements \App\Application\Shared\Contracts\InventoryManagerInterface, \App\Contracts\InventoryManagerInterface
{
    public function __construct(
        private ?InventoryProductLocker $locker = null,
        private ?StockMutationService $stockMutations = null,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @return Collection<int, Product>
     *
     * @throws ValidationException
     */
    public function validateAndLock(array $cartItems, int|string $tenantId): Collection
    {
        $productIds = $this->productIdsFromCart($cartItems);
        if ($productIds === []) {
            return collect();
        }

        $quantityMap = $this->quantityMapFromCart($cartItems);
        $lockedProducts = $this->locker()->lockForCheckout($productIds, $tenantId);
        $events = new InventoryEventBuffer;

        foreach ($quantityMap as $productId => $totalRequested) {
            $product = $lockedProducts->get($productId);
            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => "Producto ID {$productId} no encontrado o no disponible.",
                ]);
            }

            if ($product->manage_stock) {
                $this->stockMutations()->validateAndDecrementForCheckout($product, (int) $totalRequested, $events);
            } else {
                $this->stockMutations()->validateUnmanaged($product);
            }
        }

        $events->flushAfterCommit();

        return $lockedProducts;
    }

    public function restock(Order $order): void
    {
        $order->loadMissing('items');

        /** @var int|string|null $orderTenantId */
        $orderTenantId = $order->getRawOriginal('tenant_id');

        // VC-AUDIT-102: Prevent scope bypass if tenant_id is missing
        if ($orderTenantId === null || $orderTenantId === '') {
            throw new \LogicException('Cannot restock inventory: Order tenant_id is missing.');
        }

        $productIds = $this->productIdsFromOrder($order);
        if ($productIds === []) {
            return;
        }

        $quantityMap = $this->quantityMapFromOrder($order);

        DB::transaction(function () use ($productIds, $quantityMap, $order, $orderTenantId): void {
            $lockedProducts = $this->locker()->lockForOrderMutation($productIds, $orderTenantId);
            $events = new InventoryEventBuffer;

            foreach ($quantityMap as $productId => $restoreQty) {
                $product = $lockedProducts->get($productId);
                if (! $product) {
                    continue;
                }

                $this->stockMutations()->restore($product, (int) $restoreQty, $order, $events);
            }

            $events->flushAfterCommit();
        });
    }

    public function consume(Order $order): void
    {
        $order->loadMissing('items');

        /** @var int|string|null $orderTenantId */
        $orderTenantId = $order->getRawOriginal('tenant_id');

        if ($orderTenantId === null || $orderTenantId === '') {
            throw new \LogicException('Cannot consume inventory: Order tenant_id is missing.');
        }

        $productIds = $this->productIdsFromOrder($order);
        if ($productIds === []) {
            return;
        }

        $quantityMap = $this->quantityMapFromOrder($order);

        DB::transaction(function () use ($productIds, $quantityMap, $order, $orderTenantId): void {
            // VC-AUDIT-104: Use Eloquent to ensure Enum casting
            $lockedProducts = $this->locker()->lockForOrderMutation($productIds, $orderTenantId);
            $events = new InventoryEventBuffer;

            foreach ($quantityMap as $productId => $requiredQty) {
                $product = $lockedProducts->get($productId);
                if (! $product) {
                    continue;
                }

                $this->stockMutations()->consume($product, (int) $requiredQty, $order, $events);
            }

            $events->flushAfterCommit();
        });
    }

    private function locker(): InventoryProductLocker
    {
        return $this->locker ??= new InventoryProductLocker;
    }

    private function stockMutations(): StockMutationService
    {
        return $this->stockMutations ??= new StockMutationService;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @return array<int, int|string>
     */
    private function productIdsFromCart(array $cartItems): array
    {
        return collect($cartItems)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     * @return array<int|string, int|float>
     */
    private function quantityMapFromCart(array $cartItems): array
    {
        return collect($cartItems)
            ->filter(fn ($item) => ! empty($item['product_id']))
            ->groupBy('product_id')
            ->map(fn ($items) => $items->sum('quantity'))
            ->toArray();
    }

    /**
     * @return array<int, int|string>
     */
    private function productIdsFromOrder(Order $order): array
    {
        return $order->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * @return array<int|string, int|float>
     */
    private function quantityMapFromOrder(Order $order): array
    {
        return $order->items
            ->filter(fn ($item) => ! empty($item->product_id))
            ->groupBy('product_id')
            ->map(fn ($items) => $items->sum('quantity'))
            ->toArray();
    }
}
