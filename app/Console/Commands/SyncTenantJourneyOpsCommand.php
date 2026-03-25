<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\TenantGovernance\Services\TenantJourneyOpsMonitor;
use Illuminate\Console\Command;

class SyncTenantJourneyOpsCommand extends Command
{
    protected $signature = 'crm:sync-journey-ops {--limit=50 : Numero maximo de negocios a analizar}';

    protected $description = 'Abre playbooks humanos cuando los journeys CRM pierden conversion, no-show o recompra.';

    public function handle(TenantJourneyOpsMonitor $monitor): int
    {
        $result = $monitor->syncSignals((int) $this->option('limit'));

        $this->info(sprintf(
            'Journey ops sincronizado: conversion=%d, no_show=%d, reward=%d',
            (int) ($result['conversion'] ?? 0),
            (int) ($result['no_show'] ?? 0),
            (int) ($result['reward'] ?? 0),
        ));

        return self::SUCCESS;
    }
}
