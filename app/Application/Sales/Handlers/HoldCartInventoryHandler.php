<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Inventory\Contracts\InventoryHoldPortInterface;
use App\Application\Sales\Commands\HoldCartInventoryCommand;
use App\Application\Shared\Contracts\TransactionManagerInterface;

final class HoldCartInventoryHandler
{
    public function __construct(
        private readonly InventoryHoldPortInterface $holdPort,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    public function handle(HoldCartInventoryCommand $command): void
    {
        $this->transactionManager->transaction(function () use ($command) {
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
