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
        {--json : Emit the complete deterministic report as JSON}';

    protected $description = 'Preflight or expand canonical CRM tenant-integrity structures without backfill or contract writes.';

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

        try {
            $report = $integrity->run($phase, $fingerprint);
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
            CrmTenantIntegrity::STATUS_EXPANDED_NOOP => self::SUCCESS,
            CrmTenantIntegrity::STATUS_BLOCKED_UNKNOWN => 6,
            default => 5,
        };
    }
}
