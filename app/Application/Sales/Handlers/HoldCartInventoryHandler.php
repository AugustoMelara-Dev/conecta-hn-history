<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Sales\Commands\HoldCartInventoryCommand;
use App\Application\Sales\Contracts\InventoryHoldPortInterface;

final class HoldCartInventoryHandler
{
    public function __construct(
        private readonly InventoryHoldPortInterface $holdPort
    ) {}

    public function handle(HoldCartInventoryCommand $command): void
    {
        $this->holdPort->createHolds(
            $command->tenantId,
            $command->sessionId,
            $command->items,
            $command->expiresInMinutes
        );
    }
}
