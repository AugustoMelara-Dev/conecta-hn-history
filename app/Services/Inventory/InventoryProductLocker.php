<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class InventoryProductLocker
{
    /**
     * @param  array<int, int|string>  $productIds
     * @return Collection<int, Product>
     */
    public function lockForCheckout(array $productIds, int|string $tenantId): Collection
    {
        $rawProducts = DB::table('products')
            ->whereIn('id', $productIds)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return Product::hydrate($rawProducts->toArray())->keyBy('id');
    }

    /**
     * @param  array<int, int|string>  $productIds
     * @return Collection<int, Product>
     */
    public function lockForOrderMutation(array $productIds, int|string $tenantId): Collection
    {
        // VC-AUDIT-104: Use Eloquent to ensure Enum casting (not DB::table+hydrate)
        return Product::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $productIds)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }
}
