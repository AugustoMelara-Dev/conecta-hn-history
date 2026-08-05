<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReconcileInventoryMovementsCommand extends Command
{
    protected $signature = 'inventory:reconcile-movements {--tenant= : Limit reconciliation to one tenant UUID}';

    protected $description = 'Read-only reconciliation of inventory movement chains and product stock.';

    public function handle(): int
    {
        $query = DB::table('inventory_movements')
            ->orderBy('tenant_id')
            ->orderBy('product_id')
            ->orderBy('sequence');

        if (is_string($this->option('tenant')) && $this->option('tenant') !== '') {
            $query->where('tenant_id', $this->option('tenant'));
        }

        $movements = $query->get();
        $issues = 0;
        $lastByProduct = [];

        foreach ($movements as $movement) {
            $key = $movement->tenant_id.':'.$movement->product_id;
            $previous = $lastByProduct[$key] ?? null;

            if ((int) $movement->delta === 0 || (int) $movement->previous_quantity + (int) $movement->delta !== (int) $movement->resulting_quantity) {
                $this->error("structural_invalid movement={$movement->id} delta={$movement->delta} previous={$movement->previous_quantity} resulting={$movement->resulting_quantity}");
                $issues++;
            }

            if ($previous !== null && (int) $previous->resulting_quantity !== (int) $movement->previous_quantity) {
                $this->error("chain_mismatch movement={$movement->id} expected_previous={$previous->resulting_quantity} actual_previous={$movement->previous_quantity}");
                $issues++;
            }

            $lastByProduct[$key] = $movement;
        }

        foreach ($lastByProduct as $movement) {
            $stock = DB::table('products')
                ->where('id', $movement->product_id)
                ->where('tenant_id', $movement->tenant_id)
                ->value('stock_quantity');

            if ($stock === null) {
                $this->error("missing_product movement={$movement->id} product={$movement->product_id}");
                $issues++;

                continue;
            }

            if ((int) $stock !== (int) $movement->resulting_quantity) {
                $this->error("stock_mismatch product={$movement->product_id} ledger={$movement->resulting_quantity} stock={$stock}");
                $issues++;
            }
        }

        $this->info("Checked {$movements->count()} inventory movements; issues={$issues}.");

        return $issues === 0 ? self::SUCCESS : self::FAILURE;
    }
}
