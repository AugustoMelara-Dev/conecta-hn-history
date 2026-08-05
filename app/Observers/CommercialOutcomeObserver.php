<?php

declare(strict_types=1);

namespace App\Observers;

use App\Application\Analytics\TrustedCommercialOutcomeProjector;
use App\Models\Appointment;
use App\Models\Order;

final readonly class CommercialOutcomeObserver
{
    public function __construct(
        private TrustedCommercialOutcomeProjector $projector,
    ) {}

    public function saved(Order|Appointment $aggregate): void
    {
        if ($aggregate instanceof Order) {
            if (! $aggregate->wasRecentlyCreated && ! $aggregate->wasChanged([
                'status',
                'payment_status',
                'paid_at',
                'refunded_at',
                'total',
                'currency',
            ])) {
                return;
            }

            $this->projector->projectOrder(
                (string) $aggregate->tenant_id,
                (int) $aggregate->getKey(),
            );

            return;
        }

        if (! $aggregate->wasRecentlyCreated && ! $aggregate->wasChanged('status')) {
            return;
        }

        $this->projector->projectAppointment(
            (string) $aggregate->tenant_id,
            (int) $aggregate->getKey(),
        );
    }
}
