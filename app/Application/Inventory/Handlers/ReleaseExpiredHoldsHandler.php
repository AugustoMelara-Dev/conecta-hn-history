<?php

declare(strict_types=1);

namespace App\Application\Inventory\Handlers;

use App\Application\Inventory\Contracts\InventoryHoldPortInterface;

final class ReleaseExpiredHoldsHandler
{
    public function __construct(
        private readonly InventoryHoldPortInterface $inventoryHoldPort
    ) {}

    public function handle(): void
    {
        $this->inventoryHoldPort->releaseExpired();
    }
}
