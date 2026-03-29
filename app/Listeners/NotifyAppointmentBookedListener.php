<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Contracts\AdminNotifier;
use App\Domain\TenantGovernance\Services\OperationalPlaybookTaskManager;
use App\Events\Appointment\AppointmentCreated;
use App\Models\Appointment;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class NotifyAppointmentBookedListener implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(
        private readonly AdminNotifier $notifier,
        private readonly OperationalPlaybookTaskManager $playbookTaskManager,
    ) {}

    public function handle(AppointmentCreated $event): void
    {
        $appointmentId = $event->appointment->getKey();
        $appointment = Appointment::query()
            ->with(['tenant', 'service', 'provider'])
            ->find($appointmentId);

        if (! $appointment) {
            return;
        }

        $tenant = $appointment->tenant;

        if ($tenant instanceof Tenant) {
            $this->playbookTaskManager->syncForTrigger($tenant, 'appointment_booking', [
                'related' => $appointment,
                'customer_name' => (string) ($appointment->customer_display_name ?? 'este cliente'),
                'service_name' => (string) ($appointment->service?->name ?? 'el servicio'),
            ]);
        }

        $this->notifier->notifyAppointmentBooked($appointment);
    }
}
