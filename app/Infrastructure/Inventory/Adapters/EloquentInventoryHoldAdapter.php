<?php

declare(strict_types=1);

namespace App\Infrastructure\Inventory\Adapters;

use App\Application\Inventory\Contracts\InventoryHoldPortInterface;
use App\Domain\Inventory\Enums\MovementType;
use App\Models\InventoryHold;
use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EloquentInventoryHoldAdapter implements InventoryHoldPortInterface
{
    private ?bool $inventoryHoldUsesStringPrimaryKey = null;

    public function reserve(
        string $tenantId,
        string $locationId,
        string $sessionId,
        int $productId,
        float $quantity,
        int $expiresInMinutes = 15
    ): void {
        DB::transaction(function () use ($tenantId, $locationId, $sessionId, $productId, $quantity, $expiresInMinutes) {
            $this->assertValidLocation($tenantId, $locationId);

            $product = Product::query()
                ->withoutGlobalScopes()
                ->where('id', $productId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (! $product) {
                throw new DomainException("Product {$productId} not found or does not belong to tenant.");
            }

            $hold = InventoryHold::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('session_id', $sessionId)
                ->where('location_id', $locationId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $oldQuantity = $hold !== null ? (float) $hold->quantity : 0.0;
            $newQuantity = round($quantity, 3);
            $delta = round($newQuantity - $oldQuantity, 3);

            $level = null;

            if ($product->manage_stock) {
                InventoryLevel::query()
                    ->withoutGlobalScopes()
                    ->firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'location_id' => $locationId,
                            'product_id' => $productId,
                        ],
                        [
                            'on_hand' => 0,
                            'reserved' => 0,
                            'low_stock_threshold' => $product->low_stock_threshold,
                        ]
                    );

                $level = InventoryLevel::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('location_id', $locationId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                if ($level === null) {
                    throw new DomainException("Inventory level missing for product {$productId} at location {$locationId}.");
                }

                if ($delta > 0 && ! $product->allow_backorders) {
                    $available = round((float) $level->on_hand - (float) $level->reserved, 3);

                    if ($available < $delta) {
                        throw new DomainException("Insufficient stock available for product {$productId}.");
                    }
                }

                if ($delta !== 0.0) {
                    $level->reserved = max(0.0, round((float) $level->reserved + $delta, 3));
                    $level->save();
                }
            }

            if ($newQuantity <= 0.0) {
                $hold?->delete();

                return;
            }

            $this->upsertInventoryHold(
                tenantId: $tenantId,
                sessionId: $sessionId,
                locationId: $locationId,
                productId: $productId,
                quantity: $newQuantity,
                expiresInMinutes: $expiresInMinutes
            );
        });
    }

    public function commit(string $tenantId, string $sessionId, string|int|null $referenceId = null): void
    {
        DB::transaction(function () use ($tenantId, $sessionId, $referenceId) {
            $holds = InventoryHold::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('session_id', $sessionId)
                ->lockForUpdate()
                ->get();

            foreach ($holds as $hold) {
                $product = Product::query()
                    ->withoutGlobalScopes()
                    ->withTrashed()
                    ->where('id', $hold->product_id)
                    ->where('tenant_id', $tenantId)
                    ->first();

                $level = InventoryLevel::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('location_id', $hold->location_id)
                    ->where('product_id', $hold->product_id)
                    ->lockForUpdate()
                    ->first();

                if (($product?->manage_stock || $level !== null) && $level === null) {
                    throw new DomainException("Inventory level missing for product {$hold->product_id} at location {$hold->location_id}.");
                }

                if ($level !== null) {
                    $quantity = round((float) $hold->quantity, 3);

                    $allowsBackorders = $product instanceof Product
                        ? (bool) $product->allow_backorders
                        : false;

                    if (! $allowsBackorders && (float) $level->on_hand < $quantity) {
                        throw new DomainException("Insufficient on-hand stock to commit product {$hold->product_id}.");
                    }

                    $level->on_hand = round((float) $level->on_hand - $quantity, 3);
                    $level->reserved = max(0.0, round((float) $level->reserved - $quantity, 3));
                    $level->save();

                    InventoryMovement::query()->create([
                        'tenant_id' => $tenantId,
                        'location_id' => $hold->location_id,
                        'product_id' => $hold->product_id,
                        'quantity_delta' => -$quantity,
                        'type' => MovementType::SALE,
                        'reference_type' => $referenceId !== null ? 'order' : 'inventory_hold_commit',
                        'reference_id' => $referenceId !== null ? (string) $referenceId : null,
                        'notes' => "Order fulfilled for session {$sessionId}",
                    ]);
                }

                $hold->delete();
            }
        });
    }

    public function releaseExpired(): int
    {
        return $this->releaseExpiredForTenant();
    }

    public function releaseExpiredForTenant(?string $tenantId = null): int
    {
        return DB::transaction(function () use ($tenantId) {
            $query = InventoryHold::query()
                ->withoutGlobalScopes()
                ->where('expires_at', '<=', now())
                ->lockForUpdate();

            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }

            $holds = $query->get();
            $released = 0;

            foreach ($holds as $hold) {
                $level = InventoryLevel::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $hold->tenant_id)
                    ->where('location_id', $hold->location_id)
                    ->where('product_id', $hold->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($level !== null) {
                    $level->reserved = max(0.0, round((float) $level->reserved - (float) $hold->quantity, 3));
                    $level->save();
                }

                $hold->delete();
                $released++;
            }

            return $released;
        });
    }

    private function assertValidLocation(string $tenantId, string $locationId): void
    {
        $exists = Location::query()
            ->where('id', $locationId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return;
        }

        throw new DomainException("Invalid or inactive location {$locationId}.");
    }

    private function upsertInventoryHold(
        string $tenantId,
        string $sessionId,
        string $locationId,
        int $productId,
        float $quantity,
        int $expiresInMinutes
    ): void {
        $where = [
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'location_id' => $locationId,
            'product_id' => $productId,
        ];

        $timestamps = ['updated_at' => now()];
        $values = [
            'quantity' => $quantity,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ];

        $updatedRows = DB::table('inventory_holds')
            ->where($where)
            ->update(array_merge($values, $timestamps));

        if ($updatedRows > 0) {
            return;
        }

        $insert = array_merge($where, $values, $timestamps, [
            'created_at' => now(),
        ]);

        if ($this->inventoryHoldUsesStringPrimaryKey()) {
            $insert['id'] = (string) Str::uuid();
        }

        DB::table('inventory_holds')->insert($insert);
    }

    private function inventoryHoldUsesStringPrimaryKey(): bool
    {
        if (is_bool($this->inventoryHoldUsesStringPrimaryKey)) {
            return $this->inventoryHoldUsesStringPrimaryKey;
        }

        $idColumn = collect(Schema::getColumns('inventory_holds'))
            ->firstWhere('name', 'id');

        $idType = strtolower((string) ($idColumn['type'] ?? $idColumn['type_name'] ?? ''));

        $this->inventoryHoldUsesStringPrimaryKey = str_contains($idType, 'char')
            || str_contains($idType, 'uuid');

        return $this->inventoryHoldUsesStringPrimaryKey;
    }
}
