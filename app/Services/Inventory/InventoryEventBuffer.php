<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Events\Inventory\LowStockThresholdReached;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class InventoryEventBuffer
{
    /** @var list<array{product_id:int|string,new_stock:int,threshold:int}> */
    private array $lowStockEvents = [];

    /** @var list<array{message:string,context:array<string,mixed>}> */
    private array $telemetry = [];

    public function captureLowStock(Product $product, int $newStock): void
    {
        $threshold = $product->low_stock_threshold;
        if ($threshold === null || $newStock > $threshold || $newStock <= 0) {
            return;
        }

        $this->lowStockEvents[] = [
            'product_id' => $product->id,
            'new_stock' => $newStock,
            'threshold' => $threshold,
        ];
    }

    /**
     * @param  array<string,mixed>  $context
     */
    public function captureTelemetry(string $operation, array $context): void
    {
        $message = match ($operation) {
            'restored' => 'Inventory restored from cancelled order',
            'consumed' => 'Inventory consumed for restored order',
            default => 'Inventory mutation completed',
        };

        $this->telemetry[] = compact('message', 'context');
    }

    public function flushAfterCommit(): void
    {
        $lowStockEvents = $this->lowStockEvents;
        $telemetry = $this->telemetry;

        DB::afterCommit(static function () use ($lowStockEvents, $telemetry): void {
            foreach ($lowStockEvents as $eventData) {
                $freshProduct = Product::find($eventData['product_id']);
                if ($freshProduct) {
                    LowStockThresholdReached::dispatch(
                        $freshProduct,
                        $eventData['new_stock'],
                        $eventData['threshold']
                    );
                }
            }

            foreach ($telemetry as $logEntry) {
                Log::debug($logEntry['message'], $logEntry['context']);
            }
        });
    }
}
