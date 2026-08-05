<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Booking\DTOs\AppointmentReminderData;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Processes one tenant's appointments scheduled within the next 23-24 hours.
 *
 * A global coordinator fans out one isolated job per active tenant. The queue
 * middleware initializes tenant context, while the query includes an explicit
 * tenant predicate as defense in depth. The reminder marker makes retries
 * idempotent and the mailable receives an immutable primitive snapshot.
 */
class SendAppointmentReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly string $tenantId) {}

    /** @return array<int, TenantQueueMiddleware> */
    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function handle(): void
    {
        // Define the 24-hour window (23h to 24h from now)
        $windowStart = now()->addHours(23);
        $windowEnd = now()->addHours(24);

        // Query only this tenant's appointments within the reminder window.
        $appointments = Appointment::query()
            ->where('tenant_id', $this->tenantId)
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->whereNull('reminder_sent_at')
            ->whereIn('status', [
                Appointment::STATUS_PENDING,
                Appointment::STATUS_CONFIRMED,
            ])
            ->get();

        if ($appointments->isEmpty()) {
            Log::info('SendAppointmentReminders: No appointments in the 24h window.');

            return;
        }

        Log::info("SendAppointmentReminders: Processing {$appointments->count()} appointments.");

        foreach ($appointments as $appointment) {
            $this->processReminder($appointment);
        }
    }

    private function processReminder(Appointment $appointment): void
    {
        Cache::lock("appointment-reminder:{$this->tenantId}:{$appointment->getKey()}", 60)
            ->get(function () use ($appointment): void {
                $this->queueReminder($appointment);
            });
    }

    private function queueReminder(Appointment $appointment): void
    {
        $appointment->refresh();

        if ($appointment->reminder_sent_at !== null) {
            return;
        }

        // Skip if no customer email
        if (empty($appointment->customer_email)) {
            Log::warning('SendAppointmentReminders: Skipped appointment (no email)', [
                'appointment_id' => $appointment->id,
            ]);

            return;
        }

        // Load required relationships
        $appointment->loadMissing(['tenant', 'service', 'provider']);

        /** @var Tenant|null $tenant */
        $tenant = $appointment->tenant;
        /** @var Service|null $service */
        $service = $appointment->service;
        /** @var ServiceProvider|null $provider */
        $provider = $appointment->provider;

        if (! $tenant || ! $service || ! $provider) {
            Log::critical('SendAppointmentReminders: Required relationship failed to load', [
                'appointment_id' => $appointment->id,
                'tenant_loaded' => $tenant !== null,
                'service_loaded' => $service !== null,
                'provider_loaded' => $provider !== null,
            ]);

            return;
        }

        // Send the reminder email (queued)
        $reminder = new AppointmentReminderData(
            appointmentId: (string) $appointment->getKey(),
            tenantId: $this->tenantId,
            tenantName: (string) $tenant->name,
            customerEmail: (string) $appointment->customer_email,
            serviceName: (string) $service->name,
            date: $appointment->scheduled_at->translatedFormat('l, d \\d\\e F \\d\\e Y'),
            time: $appointment->scheduled_at->format('h:i A'),
            providerName: (string) $provider->name,
            managementUrl: URL::signedRoute(
                'public.appointment.manage',
                ['appointment' => $appointment->getKey()],
            ),
        );

        Mail::to($reminder->customerEmail)
            ->queue(new AppointmentReminderMail($reminder));

        // Mark as reminded (idempotency flag)
        $appointment->update(['reminder_sent_at' => now()]);

        Log::info('SendAppointmentReminders: Reminder sent', [
            'appointment_id' => $appointment->id,
            'customer_email' => $appointment->customer_email,
            'scheduled_at' => $appointment->scheduled_at->toIso8601String(),
        ]);
    }
}
