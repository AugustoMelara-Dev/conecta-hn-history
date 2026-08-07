<?php

declare(strict_types=1);

namespace App\Application\Reporting;

use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportFormat;
use App\Models\ReportSchedule;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * ReportScheduler - Ejecuta scheduled reports y manda por email.
 */
class ReportScheduler
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportExecutor $executor,
        private readonly ReportExporter $exporter,
    ) {}

    /**
     * Procesa todos los schedules que están listos para ejecutarse.
     *
     * @return array{processed: int, sent: int, errors: int}
     */
    public function processDue(): array
    {
        $schedules = ReportSchedule::dueForRun()->get();
        $stats = ['processed' => 0, 'sent' => 0, 'errors' => 0];

        foreach ($schedules as $schedule) {
            $stats['processed']++;
            try {
                $this->runSchedule($schedule);
                $stats['sent']++;
            } catch (\Throwable $e) {
                Log::error('Report schedule failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        return $stats;
    }

    public function runSchedule(ReportSchedule $schedule): string
    {
        $reportKey = \App\Domain\Reporting\Enums\ReportKey::tryFrom($schedule->report_key);
        if ($reportKey === null || ! $this->registry->has($reportKey)) {
            throw new \InvalidArgumentException("Unknown report: {$schedule->report_key}");
        }

        $report = $this->registry->get($reportKey);
        $range = \App\Domain\Reporting\ValueObjects\ReportRange::last30Days();
        $format = ReportFormat::from($schedule->format);

        $path = $this->exporter->export($report, $schedule->tenant_id, $range, $format);

        // Send email to recipients
        $recipients = $schedule->recipients ?? [];
        if (! empty($recipients)) {
            foreach ($recipients as $email) {
                Mail::raw(
                    "Reporte {$schedule->report_key} adjunto",
                    function ($message) use ($email, $schedule, $path) {
                        $message->to($email)
                            ->subject("[Dovra AI] Reporte: {$schedule->report_key}")
                            ->attach($path);
                    },
                );
            }
        }

        $schedule->update([
            'last_run_at' => now(),
            'next_run_at' => $schedule->computeNextRun(),
        ]);

        return $path;
    }

    public function schedule(string $reportKey, string $tenantId, string $frequency, array $recipients, ReportFormat $format = ReportFormat::Pdf): ReportSchedule
    {
        $schedule = ReportSchedule::create([
            'tenant_id' => $tenantId,
            'report_key' => $reportKey,
            'frequency' => $frequency,
            'recipients' => $recipients,
            'format' => $format->value,
            'is_active' => true,
            'next_run_at' => now()->addDay(), // Default next run
        ]);

        $schedule->update(['next_run_at' => $schedule->computeNextRun()]);

        return $schedule->fresh();
    }
}
