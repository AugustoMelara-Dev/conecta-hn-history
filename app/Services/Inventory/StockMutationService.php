<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class StockMutationService
{
    public function validateAndDecrementForCheckout(Product $product, int $quantity, InventoryEventBuffer $events): void
    {
        $currentStock = (int) ($product->stock_quantity ?? 0);

        if ($currentStock < $quantity) {
            throw ValidationException::withMessages([
                'items' => "Stock insuficiente para '{$product->name}'. Disponible: {$currentStock}, Solicitado: {$quantity}",
            ]);
        }

        $product->decrement('stock_quantity', $quantity);
        $newStock = $currentStock - $quantity;
        $this->transitionOutOfStockIfNeeded($product, $newStock);
        $events->captureLowStock($product, $newStock);
    }

    public function validateUnmanaged(Product $product): void
    {
        if ($product->stock_status !== StockStatus::InStock) {
            throw ValidationException::withMessages([
                'items' => "Producto '{$product->name}' no disponible.",
            ]);
        }
    }

    public function restore(Product $product, int $quantity, Order $order, InventoryEventBuffer $events): void
    {
        if (! $product->manage_stock) {
            return;
        }

        $currentStock = (int) ($product->stock_quantity ?? 0);
        $newStock = $currentStock + $quantity;
        $updatePayload = ['stock_quantity' => $newStock];

        if ($product->stock_status === StockStatus::OutOfStock && $newStock > 0) {
            $updatePayload['stock_status'] = StockStatus::InStock;
        }

        $product->update($updatePayload);

        $events->captureTelemetry('restored', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity_restored' => $quantity,
            'previous_stock' => $currentStock,
            'new_stock' => $newStock,
            'order_id' => $order->id,
        ]);
    }

    public function consume(Product $product, int $quantity, Order $order, InventoryEventBuffer $events): void
    {
        if (! $product->manage_stock) {
            return;
        }

        $currentStock = (int) ($product->stock_quantity ?? 0);

        if ($currentStock < $quantity) {
            throw ValidationException::withMessages([
                'order' => "No se puede restaurar la orden: Stock insuficiente para '{$product->name}'. Disponible: {$currentStock}, Requerido: {$quantity}",
            ]);
        }

        $newStock = $currentStock - $quantity;
        $updatePayload = ['stock_quantity' => $newStock];

        $allowsBackorders = $product->allow_backorders ?? false;
        if ($newStock <= 0 && ! $allowsBackorders) {
            $updatePayload['stock_status'] = StockStatus::OutOfStock;
        }

        $product->update($updatePayload);
        $events->captureLowStock($product, $newStock);
        $events->captureTelemetry('consumed', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity_consumed' => $quantity,
            'new_stock' => $newStock,
            'order_id' => $order->id,
        ]);
    }

    private function transitionOutOfStockIfNeeded(Product $product, int $newStock): void
    {
        $allowsBackorders = $product->allow_backorders ?? false;
        if ($newStock <= 0 && ! $allowsBackorders) {
            $product->update(['stock_status' => StockStatus::OutOfStock]);
        }
    }
}
