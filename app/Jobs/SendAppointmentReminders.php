<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Contracts\AdminNotifier;
use App\Domain\TenantGovernance\Services\OperationalPlaybookTaskManager;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send Appointment Reminders Job
 *
 * Polls the database hourly for appointments scheduled within the next 23–24 hours
 * that have not yet received a reminder email.
 *
 * ARCHITECTURE:
 * - Uses "Poll-and-Dispatch" pattern with Laravel Scheduler.
 * - Idempotent: Uses `reminder_sent_at` column to prevent duplicates.
 * - Tenant-Isolated: BelongsToTenant trait ensures scoping (bypassed here for global poll).
 * - Queued Emails: Each email is dispatched to the queue, not sent inline.
 *
 * INVARIANTS:
 * - An appointment receives AT MOST one 24h reminder.
 * - Tenant, Service, and Provider relationships MUST be loaded.
 */
class SendAppointmentReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        AdminNotifier $notifier,
        ?OperationalPlaybookTaskManager $playbookTaskManager = null,
    ): void
    {
        $playbookTaskManager ??= app(OperationalPlaybookTaskManager::class);

        // Define the 24-hour window (23h to 24h from now)
        $windowStart = now()->addHours(23);
        $windowEnd = now()->addHours(24);

        // Query appointments within the window that haven't been reminded
        // NOTE: We query WITHOUT tenant scope to process ALL tenants at once.
        $appointments = Appointment::query()
            ->withoutGlobalScopes() // Bypass TenantScope for global poll
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
            $this->processReminder($appointment, $notifier, $playbookTaskManager);
        }
    }

    private function processReminder(
        Appointment $appointment,
        AdminNotifier $notifier,
        OperationalPlaybookTaskManager $playbookTaskManager,
    ): void
    {
        // Skip if no customer email
        if (empty($appointment->customer_email)) {
            Log::warning('SendAppointmentReminders: Skipped appointment (no email)', [
                'appointment_id' => $appointment->id,
            ]);

            return;
        }

        // Load required relationships
        $appointment->loadMissing(['tenant', 'service', 'provider']);

        // Validate tenant relationship
        if (! $appointment->tenant) {
            Log::critical('SendAppointmentReminders: Tenant relationship failed to load', [
                'appointment_id' => $appointment->id,
            ]);

            return;
        }

        // Send the reminder email (queued)
        Mail::to($appointment->customer_email)
            ->queue(new AppointmentReminderMail($appointment));

        // Mark as reminded (idempotency flag)
        $appointment->update(['reminder_sent_at' => now()]);

        if ($appointment->tenant instanceof Tenant) {
            $playbookTaskManager->syncForTrigger($appointment->tenant, 'appointment_reminder', [
                'related' => $appointment,
                'customer_name' => (string) ($appointment->customer_display_name ?? 'este cliente'),
                'service_name' => (string) ($appointment->service?->name ?? 'el servicio'),
            ]);
        }

        $notifier->notifyAppointmentReminder($appointment);

        Log::info('SendAppointmentReminders: Reminder sent', [
            'appointment_id' => $appointment->id,
            'customer_email' => $appointment->customer_email,
            'scheduled_at' => $appointment->scheduled_at->toIso8601String(),
        ]);
    }
}
