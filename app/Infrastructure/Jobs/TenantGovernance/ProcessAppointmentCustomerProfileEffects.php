<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\TenantGovernance;

use App\Domain\TenantGovernance\Services\CustomerJourneySignalRecorder;
use App\Domain\TenantGovernance\Services\CustomerLifecycleTriggerManager;
use App\Domain\TenantGovernance\Services\CustomerProfileManager;
use App\Infrastructure\Queue\Middleware\TenantAwareMiddleware;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessAppointmentCustomerProfileEffects implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $appointmentId,
        public readonly ?int $originalProfileId,
        public readonly bool $shouldRecordSignals,
        public readonly bool $shouldTriggerLifecycle,
    ) {}

    public function middleware(): array
    {
        return [new TenantAwareMiddleware];
    }

    public function handle(
        CustomerProfileManager $profileManager,
        CustomerJourneySignalRecorder $signalRecorder,
        CustomerLifecycleTriggerManager $lifecycleTriggerManager,
    ): void {
        $appointment = Appointment::query()
            ->withoutGlobalScopes()
            ->find($this->appointmentId);

        if (! $appointment instanceof Appointment) {
            return;
        }

        $profile = $profileManager->syncAppointment(
            $appointment,
            $this->originalProfileId,
        );

        if ($profile !== null && $this->shouldRecordSignals) {
            $signalKey = $appointment->status === Appointment::STATUS_NO_SHOW
                ? 'appointment_no_show'
                : ($appointment->status === Appointment::STATUS_COMPLETED ? 'appointment_completed' : 'appointment_booked');

            $conversation = $signalRecorder->recordForProfile(
                $profile,
                $signalKey,
                [
                    'appointment_id' => (int) $appointment->getKey(),
                    'appointment_status' => (string) $appointment->status,
                    'scheduled_for' => optional($appointment->scheduled_at)->toIso8601String(),
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
                        'scheduled_for' => optional($appointment->scheduled_at)->toIso8601String(),
                    ],
                    'crm',
                );
            }
        }

        if ($this->shouldTriggerLifecycle) {
            $lifecycleTriggerManager->handleAppointmentSaved($appointment->fresh());
        }
    }
}
