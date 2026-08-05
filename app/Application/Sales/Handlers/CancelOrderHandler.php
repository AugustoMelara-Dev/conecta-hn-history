<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Orders\CanonicalOrderWriter;
use App\Application\Orders\Commands\TransitionOrderCommand;
use App\Application\Sales\Commands\CancelOrderCommand;
use App\Application\Sales\Results\CancelOrderResult;
use App\Models\Order;

/**
 * Compatibility adapter for callers still dispatching the legacy cancellation command.
 */
final readonly class CancelOrderHandler
{
    public function __construct(private CanonicalOrderWriter $writer) {}

    public function handle(CancelOrderCommand $command): CancelOrderResult
    {
        $result = $this->writer->cancel(new TransitionOrderCommand(
            tenantId: $command->tenantId,
            actorId: (string) $command->userId,
            orderId: $command->orderId,
            targetStatus: Order::STATUS_CANCELLED,
            reason: $command->reason,
        ));

        return CancelOrderResult::fromEntity($result->order, $command->reason);
    }
}
