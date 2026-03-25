<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\TenantGovernance\Services\CustomerLifecycleTriggerManager;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class DispatchCustomerRetentionAutomationsCommand extends Command
{
    protected $signature = 'crm:dispatch-retention {--tenant=} {--limit=25}';

    protected $description = 'Dispara automatizaciones de retencion, recompra e inactividad para la cartera de clientes';

    public function handle(CustomerLifecycleTriggerManager $triggerManager): int
    {
        $tenantId = $this->option('tenant');
        $limit = (int) $this->option('limit');

        if (filled($tenantId)) {
            $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($tenantId);

            $result = $triggerManager->runReactivationSweep($tenant, null, $limit);

            $this->components->info("Tenant {$tenant->name}: procesados {$result['processed']}, enviados {$result['sent']}, omitidos {$result['skipped']}, fallidos {$result['failed']}.");

            return self::SUCCESS;
        }

        Tenant::query()
            ->withoutGlobalScopes()
            ->lazyById(100)
            ->each(function (Tenant $tenant) use ($triggerManager, $limit): void {
                $result = $triggerManager->runReactivationSweep($tenant, null, $limit);

                if ($result['processed'] > 0) {
                    $this->line("{$tenant->name}: {$result['sent']} enviados / {$result['skipped']} omitidos / {$result['failed']} fallidos.");
                }
            });

        $this->components->info('Barrido de retencion completado.');

        return self::SUCCESS;
    }
}
