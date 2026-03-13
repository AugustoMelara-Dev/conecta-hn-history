<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Sales\Commands\SyncCheckoutSessionCommand;
use App\Application\Sales\Contracts\CheckoutSessionPortInterface;

final class SyncCheckoutSessionHandler
{
    public function __construct(
        private readonly CheckoutSessionPortInterface $port
    ) {}

    public function handle(SyncCheckoutSessionCommand $command): void
    {
        $this->port->upsertSession(
            $command->tenantId,
            $command->sessionId,
            $command->items,
            $command->subtotal,
            $command->userId
        );
    }
}
