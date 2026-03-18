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
 */
class LogSalesExportActivity
{
    /**
     * Handle the SalesExportCreated event.
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
            \Spatie\Activitylog\Facades\Activity::withoutLogs(function () use ($event) {
                activity('export')
                    ->causedBy($event->userId)
                    ->withProperties([
                        'export_id' => $event->exportId,
                        'tenant_id' => $event->tenantId->toString(),
                        'format' => $event->format,
                        'criteria_hash' => $event->criteriaHash,
                        'criteria' => $event->criteriaDetails,
                    ])
                    ->log('sales_export_created');
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to persist export audit to activity log', [
                'error' => $e->getMessage(),
                'export_id' => $event->exportId,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(SalesExportCreated $event, \Throwable $exception): void
    {
        Log::error('Failed to log sales export activity', [
            'export_id' => $event->exportId,
            'tenant_id' => $event->tenantId->toString(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
