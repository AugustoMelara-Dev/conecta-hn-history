<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Contracts\AppointmentRepositoryInterface;
use App\Application\Tenant\Commands\CancelAppointmentCommand;
use App\Domain\Booking\Events\AppointmentCancelled;
use App\Domain\Shared\Contracts\EventDispatcherInterface;

final class CancelAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(CancelAppointmentCommand $command): void
    {
        $appointment = $this->appointmentRepository->findById($command->appointmentId);

        if ($appointment && $appointment->canBeCancelled()) {
            $appointment->cancel();

            $this->eventDispatcher->dispatch(new AppointmentCancelled(
                appointmentId: $appointment->id,
                tenantId: $appointment->tenant_id,
                serviceId: $appointment->service_id,
                scheduledAt: $appointment->scheduled_at->toIso8601String(),
            ));
        }
    }
}
