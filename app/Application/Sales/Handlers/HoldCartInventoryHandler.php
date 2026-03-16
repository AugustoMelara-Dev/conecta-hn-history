<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Inventory\Contracts\InventoryHoldPortInterface;
use App\Application\Sales\Commands\HoldCartInventoryCommand;
use Illuminate\Support\Facades\DB;

final class HoldCartInventoryHandler
{
    public function __construct(
        private readonly InventoryHoldPortInterface $holdPort
    ) {}

    public function handle(HoldCartInventoryCommand $command): void
    {
        DB::transaction(function () use ($command) {
            foreach ($command->items as $item) {
                $this->holdPort->reserve(
                    (string) $command->tenantId,
                    $command->locationId,
                    $command->sessionId,
                    (int) $item['product_id'],
                    (float) $item['quantity'],
                    $command->expiresInMinutes
                );
            }
        });
    }
}
