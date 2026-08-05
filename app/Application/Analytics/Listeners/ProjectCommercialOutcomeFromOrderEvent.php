<?php

declare(strict_types=1);

namespace App\Application\Analytics\Listeners;

use App\Application\Analytics\TrustedCommercialOutcomeProjector;
use App\Events\Order\OrderCancelled;
use App\Events\Order\OrderDelivered;
use App\Events\Order\OrderPaid;

final readonly class ProjectCommercialOutcomeFromOrderEvent
{
    public function __construct(
        private TrustedCommercialOutcomeProjector $projector,
    ) {}

    public function handle(OrderPaid|OrderDelivered|OrderCancelled $event): void
    {
        $this->projector->projectOrder(
            $event->tenantId()->toString(),
            (int) $event->aggregateId(),
        );
    }
}
