<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Sales\Commands\DetectAbandonedCartsCommand;
use App\Application\Sales\Contracts\CheckoutSessionPortInterface;
use App\Domain\Sales\Events\CartAbandoned;
use App\Domain\Shared\Contracts\EventDispatcherInterface;

final class DetectAbandonedCartsHandler
{
    public function __construct(
        private readonly CheckoutSessionPortInterface $port,
        private readonly EventDispatcherInterface $dispatcher
    ) {}

    public function handle(DetectAbandonedCartsCommand $command): void
    {
        $abandonedSessions = $this->port->markAsAbandoned($command->timeoutMinutes);

        foreach ($abandonedSessions as $sessionData) {
            $event = new CartAbandoned(
                checkoutSessionId: $sessionData['id'],
                tenantId: $sessionData['tenant_id'],
                sessionId: $sessionData['session_id'],
                userId: $sessionData['user_id'],
                subtotal: $sessionData['subtotal']
            );

            $this->dispatcher->dispatch($event);
        }
    }
}
