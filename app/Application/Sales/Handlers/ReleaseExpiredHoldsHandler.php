<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Sales\Commands\ReleaseExpiredHoldsCommand;
use App\Application\Sales\Contracts\InventoryHoldPortInterface;
use Psr\Log\LoggerInterface;

final class ReleaseExpiredHoldsHandler
{
    public function __construct(
        private readonly InventoryHoldPortInterface $holdPort,
        private readonly LoggerInterface $logger
    ) {}

    public function handle(ReleaseExpiredHoldsCommand $command): void
    {
        $releasedCount = $this->holdPort->releaseAllExpired();

        if ($releasedCount > 0) {
            $this->logger->info("Inventory Holds Sweep: Released {$releasedCount} expired holds system-wide.");
        }
    }
}
