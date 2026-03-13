<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales\Adapters;

use App\Application\Sales\Contracts\InventoryHoldPortInterface;
use App\Models\InventoryHold;
use App\Models\Product;
use DomainException;
use Illuminate\Support\Facades\DB;

class EloquentInventoryHoldAdapter implements InventoryHoldPortInterface
{
    public function createHolds(string|int $tenantId, string $sessionId, array $items, int $expiresInMinutes): void
    {
        if (empty($items)) {
            return;
        }

        DB::transaction(function () use ($tenantId, $sessionId, $items, $expiresInMinutes) {
            $productIds = collect($items)->pluck('product_id')->sort()->values()->toArray();

            $products = Product::whereIn('id', $productIds)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    throw new DomainException("Product {$item['product_id']} not found or does not belong to tenant.");
                }

                if ($product->manage_stock) {
                    $activeHolds = InventoryHold::where('product_id', $product->id)
                        ->where('tenant_id', $tenantId)
                        ->where('expires_at', '>', now())
                        ->where('session_id', '!=', $sessionId)
                        ->sum('quantity');

                    $available = $product->stock_quantity - $activeHolds;

                    if ($available < $item['quantity']) {
                        throw new DomainException("Insufficient available stock for product #{$product->id}.");
                    }
                }

                InventoryHold::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'session_id' => $sessionId,
                        'product_id' => $product->id,
                    ],
                    [
                        'quantity' => $item['quantity'],
                        'expires_at' => now()->addMinutes($expiresInMinutes),
                    ]
                );
            }
        });
    }

    public function releaseExpired(string|int $tenantId): int
    {
        return InventoryHold::where('tenant_id', $tenantId)
            ->where('expires_at', '<=', now())
            ->delete();
    }
}
