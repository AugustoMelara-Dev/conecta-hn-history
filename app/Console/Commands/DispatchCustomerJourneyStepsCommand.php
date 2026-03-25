<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\TenantGovernance\Services\CustomerJourneyExecutionManager;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class DispatchCustomerJourneyStepsCommand extends Command
{
    protected $signature = 'crm:dispatch-journeys {--tenant=} {--limit=50}';

    protected $description = 'Ejecuta los pasos automaticos vencidos de los journeys CRM por cliente';

    public function handle(CustomerJourneyExecutionManager $manager): int
    {
        $tenantId = $this->option('tenant');
        $limit = max(1, (int) $this->option('limit'));
        $tenant = null;

        if (filled($tenantId)) {
            $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($tenantId);
        }

        $result = $manager->dispatchDueJourneys($tenant, $limit);

        $this->components->info(
            "Journeys procesados: {$result['processed']}. Enviados: {$result['sent']}. Saltados: {$result['skipped']}. Fallidos: {$result['failed']}."
        );

        return self::SUCCESS;
    }
}
