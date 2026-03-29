<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Infrastructure\Jobs\TenantGovernance\ProcessAppointmentCustomerProfileEffects;
use App\Models\Appointment;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class AppointmentCustomerProfileObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Appointment $appointment): void
    {
        ProcessAppointmentCustomerProfileEffects::dispatch(
            tenantId: (string) $appointment->tenant_id,
            appointmentId: (int) $appointment->getKey(),
            originalProfileId: $this->originalProfileId($appointment),
            shouldRecordSignals: $appointment->wasRecentlyCreated || $appointment->wasChanged('status'),
            shouldTriggerLifecycle: $appointment->wasRecentlyCreated || ($appointment->wasChanged('status') && in_array($appointment->status, [
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_COMPLETED,
            ], true)),
        )->afterCommit();
    }

    public function deleted(Appointment $appointment): void
    {
        app(CustomerProfileManager::class)->syncProfileById(
            $this->originalProfileId($appointment) ?? $this->currentProfileId($appointment),
        );
    }

    private function originalProfileId(Appointment $appointment): ?int
    {
        $value = $appointment->getOriginal('customer_profile_id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function currentProfileId(Appointment $appointment): ?int
    {
        $value = $appointment->getAttribute('customer_profile_id');

        return is_numeric($value) ? (int) $value : null;
    }
}
