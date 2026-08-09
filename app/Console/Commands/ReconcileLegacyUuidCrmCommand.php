<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Crm\LegacyUuid\CrmLegacyUuidReconciliation;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ReconcileLegacyUuidCrmCommand extends Command
{
    protected $signature = 'crm:reconcile-legacy-uuid
        {phase? : expand, backfill, validate, or contract}
        {--phase= : Explicit phase override}
        {--limit=100 : Bounded source batch size}
        {--offset=0 : Bounded source offset}
        {--dry-run : Backfill without mutations}
        {--json : Emit the complete deterministic report as JSON}';

    protected $description = 'Expand, backfill, or validate legacy UUID CRM deals without cutover.';

    public function handle(CrmLegacyUuidReconciliation $reconciliation): int
    {
        $phaseOption = $this->option('phase');
        $phase = is_string($phaseOption) && trim($phaseOption) !== ''
            ? $phaseOption
            : ((string) ($this->argument('phase') ?: 'validate'));
        $limit = $this->integerOption('limit', 100);
        $offset = $this->integerOption('offset', 0);
        if ($limit === null || $offset === null) {
            $this->error('limit and offset must be bounded non-negative integers (limit must be positive).');

            return self::INVALID;
        }

        try {
            $report = $reconciliation->run($phase, $limit, $offset, (bool) $this->option('dry-run'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $payload = $report->toArray();
        if ((bool) $this->option('json')) {
            $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->line(str_replace(["\r", "\n"], ' ', $encoded));
        } else {
            $this->line('status: '.$report->status);
            $this->line('shape: '.$report->shape);
            $this->line('phase: '.$report->phase);
            $this->line('mapped_rows: '.$report->mappedRows);
            $this->line('quarantined_rows: '.$report->quarantinedRows);
            $this->line('child_gaps: '.$report->childGaps);
        }

        return match ($report->status) {
            'canonical_noop', 'legacy_ready', 'legacy_pending' => self::SUCCESS,
            'legacy_quarantined', 'blocked' => 5,
            'unknown_shape' => 6,
            default => self::FAILURE,
        };
    }

    private function integerOption(string $name, int $default): ?int
    {
        $raw = $this->option($name);
        $raw = $raw === null || $raw === '' ? (string) $default : (string) $raw;
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        $value = (int) $raw;
        if ($name === 'limit' && $value < 1) {
            return null;
        }
        if ($name === 'offset' && $value < 0) {
            return null;
        }

        return $value;
    }
}
