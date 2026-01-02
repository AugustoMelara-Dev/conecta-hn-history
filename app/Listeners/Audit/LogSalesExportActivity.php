<?php

declare(strict_types=1);

namespace App\Listeners\Audit;

use App\Events\Audit\SalesExportCreated;
use Illuminate\Support\Facades\Log;

/**
 * LogSalesExportActivity - Audit Listener for Export Operations
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  AUDIT PERSISTENCE: Closes the compliance loop                          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  This listener receives SalesExportCreated events and persists them     ║
 * ║  to the audit log. Without this, events are dispatched but lost.        ║
 * ║                                                                          ║
 * ║  COMPLIANCE DRIVERS:                                                    ║
 * ║    - GDPR Article 30: Records of processing activities                 ║
 * ║    - SOC 2: Security event logging                                     ║
 * ║    - PCI-DSS Requirement 10: Track access to sensitive data            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * REGISTRATION (EventServiceProvider.php):
 *   protected $listen = [
 *       \App\Events\Audit\SalesExportCreated::class => [
 *           \App\Listeners\Audit\LogSalesExportActivity::class,
 *       ],
 *   ];
 */
class LogSalesExportActivity
{
    /**
     * Handle the SalesExportCreated event.
     *
     * Persists the audit record to the configured log channel.
     * In production, this should write to a tamper-proof audit log
     * (e.g., CloudWatch, Elasticsearch, or database audit table).
     */
    public function handle(SalesExportCreated $event): void
    {
        // Get the audit payload from the event
        $auditPayload = $event->toAuditLog();

        // Log to the audit channel with structured data
        Log::channel($this->getAuditChannel())->info(
            'Sales export initiated',
            $auditPayload
        );

        // If Spatie Activitylog is available, also persist to database
        if (class_exists(\Spatie\Activitylog\Facades\Activity::class)) {
            $this->persistToActivityLog($event, $auditPayload);
        }
    }

    /**
     * Get the audit log channel name.
     *
     * Override this in production to use a dedicated audit channel
     * (e.g., 'audit', 'cloudwatch', 'elasticsearch').
     */
    protected function getAuditChannel(): string
    {
        return config('logging.audit_channel', 'stack');
    }

    /**
     * Persist to Spatie Activitylog if available.
     */
    protected function persistToActivityLog(SalesExportCreated $event, array $auditPayload): void
    {
        try {
            \Spatie\Activitylog\Facades\Activity::withoutLogs(function () use ($event, $auditPayload) {
                activity('export')
                    ->causedBy($event->userId)
                    ->performedOn(null) // No specific model
                    ->withProperties([
                        'export_id' => $event->exportId,
                        'tenant_id' => $event->tenantId->toInt(),
                        'format' => $event->format,
                        'criteria_hash' => $event->criteriaHash,
                        'criteria' => $event->criteriaDetails,
                    ])
                    ->log('sales_export_created');
            });
        } catch (\Throwable $e) {
            // Don't let activity log failures break the export
            Log::warning('Failed to persist export audit to activity log', [
                'error' => $e->getMessage(),
                'export_id' => $event->exportId,
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * If this listener runs async and fails, we need to know about it.
     */
    public function failed(SalesExportCreated $event, \Throwable $exception): void
    {
        Log::error('Failed to log sales export activity', [
            'export_id' => $event->exportId,
            'tenant_id' => $event->tenantId->toInt(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
