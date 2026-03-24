<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Contracts\AdminNotifier;
use App\Domain\TenantGovernance\Services\OperationalPlaybookTaskManager;
use App\Events\Appointment\AppointmentCreated;
use App\Models\Tenant;

final class NotifyAppointmentBookedListener
{
    public function __construct(
        private readonly AdminNotifier $notifier,
        private readonly OperationalPlaybookTaskManager $playbookTaskManager,
    ) {}

    public function handle(AppointmentCreated $event): void
    {
        $tenant = $event->appointment->tenant;

        if ($tenant instanceof Tenant) {
            $this->playbookTaskManager->syncForTrigger($tenant, 'appointment_booking', [
                'related' => $event->appointment,
                'customer_name' => (string) ($event->appointment->customer_display_name ?? 'este cliente'),
                'service_name' => (string) ($event->appointment->service?->name ?? 'el servicio'),
            ]);
        }

        $this->notifier->notifyAppointmentBooked($event->appointment);
    }
}
