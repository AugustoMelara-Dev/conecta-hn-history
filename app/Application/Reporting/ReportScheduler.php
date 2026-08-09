<?php

declare(strict_types=1);

namespace App\Application\Reporting;

use App\Domain\Reporting\Enums\ReportFormat;
use App\Domain\Reporting\Enums\ReportFrequency;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Models\ReportSchedule;
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
     * @return array{processed: int, generated: int, sent: int, errors: int}
     */
    public function processDue(): array
    {
        $schedules = ReportSchedule::dueForRun()->get();
        $stats = ['processed' => 0, 'generated' => 0, 'sent' => 0, 'errors' => 0];

        foreach ($schedules as $schedule) {
            $stats['processed']++;
            try {
                $this->runSchedule($schedule);
                $stats['generated']++;
                $stats['sent'] += count($schedule->recipients ?? []);
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
        $reportKey = ReportKey::tryFrom($schedule->report_key);
        if ($reportKey === null || ! $this->registry->has($reportKey)) {
            throw new \InvalidArgumentException("Unknown report: {$schedule->report_key}");
        }

        $report = $this->registry->get($reportKey);
        $range = ReportRange::last30Days();
        $format = ReportFormat::tryFrom((string) $schedule->format);
        if ($format === null) {
            throw new \InvalidArgumentException("Unsupported report format: {$schedule->format}");
        }

        $path = $this->exporter->export($report, $schedule->tenant_id, $range, $format);

        // Send email to recipients
        $recipients = array_values(array_filter(
            $schedule->recipients ?? [],
            static fn (mixed $email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        ));
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
        $key = ReportKey::tryFrom($reportKey);
        if ($key === null || ! $this->registry->has($key)) {
            throw new \InvalidArgumentException("Unknown report: {$reportKey}");
        }
        if (ReportFrequency::tryFrom($frequency) === null) {
            throw new \InvalidArgumentException("Unsupported report frequency: {$frequency}");
        }
        if (trim($tenantId) === '') {
            throw new \InvalidArgumentException('A tenant id is required to schedule a report.');
        }

        $recipients = array_values(array_unique(array_filter(
            $recipients,
            static fn (mixed $email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
        )));
        if ($recipients === []) {
            throw new \InvalidArgumentException('At least one valid report recipient is required.');
        }

        $schedule = ReportSchedule::create([
            'tenant_id' => $tenantId,
            'report_key' => $reportKey,
            'frequency' => $frequency,
            'recipients' => $recipients,
            'format' => $format->value,
            'is_active' => true,
            'next_run_at' => null,
        ]);

        $schedule->update(['next_run_at' => $schedule->computeNextRun()]);

        return $schedule->fresh();
    }
}
