<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Crm\TenantIntegrity\CrmTenantIntegrity;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ReconcileCrmTenantIntegrityCommand extends Command
{
    protected $signature = 'crm:reconcile-tenant-integrity
        {phase? : preflight, expand, backfill, validate, or contract}
        {--phase= : Explicit phase override}
        {--fingerprint= : Schema fingerprint returned by preflight}
        {--schema-fingerprint= : Alias for --fingerprint}
        {--expected-fingerprint= : Alias for --fingerprint}
        {--limit=500 : Maximum pivot rows to backfill (1-2000)}
        {--checkpoint= : JSON checkpoint file to update atomically}
        {--resume : Resume from the checkpoint lineage}
        {--dry-run : Execute and roll back the transactional batch}
        {--json : Emit the complete deterministic report as JSON}';

    protected $description = 'Preflight, expand, or deterministically backfill canonical CRM tenant-integrity structures.';

    public function handle(CrmTenantIntegrity $integrity): int
    {
        $phaseOption = $this->option('phase');
        $phase = is_string($phaseOption) && trim($phaseOption) !== ''
            ? $phaseOption
            : ((string) ($this->argument('phase') ?: 'preflight'));
        $fingerprint = $this->option('fingerprint')
            ?: $this->option('schema-fingerprint')
            ?: $this->option('expected-fingerprint');
        $fingerprint = is_string($fingerprint) && trim($fingerprint) !== '' ? trim($fingerprint) : null;
        $limit = (int) $this->option('limit');
        $checkpoint = $this->option('checkpoint');
        $checkpoint = is_string($checkpoint) && trim($checkpoint) !== '' ? trim($checkpoint) : null;

        try {
            $report = $integrity->run(
                phase: $phase,
                fingerprint: $fingerprint,
                limit: $limit,
                checkpoint: $checkpoint,
                resume: (bool) $this->option('resume'),
                dryRun: (bool) $this->option('dry-run'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        if ((bool) $this->option('json')) {
            $payload = json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->line($payload);
        } else {
            $this->line('status: '.$report->status);
            $this->line('shape: '.$report->shape);
            $this->line('phase: '.$report->phase);
            $this->line('schema_fingerprint: '.$report->schemaFingerprint);
        }

        return match ($report->status) {
            CrmTenantIntegrity::STATUS_READY,
            CrmTenantIntegrity::STATUS_EXPANDED,
            CrmTenantIntegrity::STATUS_EXPANDED_NOOP,
            CrmTenantIntegrity::STATUS_BACKFILLED,
            CrmTenantIntegrity::STATUS_BACKFILLED_NOOP,
            CrmTenantIntegrity::STATUS_BACKFILL_DRY_RUN => self::SUCCESS,
            CrmTenantIntegrity::STATUS_BLOCKED_UNKNOWN => 6,
            default => 5,
        };
    }
}
