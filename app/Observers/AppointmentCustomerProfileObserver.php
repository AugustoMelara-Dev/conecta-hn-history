<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\TenantGovernance\Services\CustomerLifecycleTriggerManager;
use App\Domain\TenantGovernance\Services\CustomerJourneySignalRecorder;
use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Models\Appointment;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class AppointmentCustomerProfileObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Appointment $appointment): void
    {
        $profile = app(CustomerProfileManager::class)->syncAppointment(
            $appointment,
            $this->originalProfileId($appointment),
        );

        if ($profile !== null && ($appointment->wasRecentlyCreated || $appointment->wasChanged('status'))) {
            $signalRecorder = app(CustomerJourneySignalRecorder::class);
            $signalKey = $appointment->status === Appointment::STATUS_NO_SHOW
                ? 'appointment_no_show'
                : ($appointment->status === Appointment::STATUS_COMPLETED ? 'appointment_completed' : 'appointment_booked');

            $conversation = $signalRecorder->recordForProfile(
                $profile,
                $signalKey,
                [
                    'appointment_id' => (int) $appointment->getKey(),
                    'appointment_status' => (string) $appointment->status,
                    'scheduled_for' => optional($appointment->start_time)->toIso8601String(),
                ],
                'crm',
            );

            if (
                $appointment->status === Appointment::STATUS_COMPLETED
                && data_get($conversation?->metadata, 'business_signals.appointment_no_show.at')
            ) {
                $signalRecorder->recordForProfile(
                    $profile,
                    'no_show_recovered',
                    [
                        'appointment_id' => (int) $appointment->getKey(),
                        'scheduled_for' => optional($appointment->start_time)->toIso8601String(),
                    ],
                    'crm',
                );
            }
        }

        if (
            $appointment->wasRecentlyCreated
            || ($appointment->wasChanged('status') && in_array($appointment->status, [
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_COMPLETED,
            ], true))
        ) {
            app(CustomerLifecycleTriggerManager::class)->handleAppointmentSaved($appointment->fresh());
        }
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
