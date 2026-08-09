<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Operations\RuntimeOperationalReadiness;
use Illuminate\Console\Command;

final class InspectRuntimeOperationalReadinessCommand extends Command
{
    protected $signature = 'dovra:operations-readiness {--json : Emit machine-readable JSON} {--strict : Fail when external providers are not verified}';

    protected $description = 'Inspect internal runtime policy and report external provider readiness without probing or simulating success.';

    public function handle(RuntimeOperationalReadiness $readiness): int
    {
        $report = $readiness->inspect();
        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->line('status='.$report['status']);
            $this->line('internal_ready='.($report['internal_ready'] ? 'true' : 'false'));
            $this->line('external_pending='.count($report['external_pending']));
        }

        if (! $report['internal_ready']) {
            return self::FAILURE;
        }

        return (bool) $this->option('strict') && $report['external_pending'] !== [] ? self::FAILURE : self::SUCCESS;
    }
}
