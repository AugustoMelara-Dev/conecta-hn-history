<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\TenantGovernance\Services\CustomerAutomationManager;
use App\Domain\TenantGovernance\Services\CustomerTouchpointGovernance;
use App\Models\CustomerTouchpoint;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class RetryCustomerTouchpointsCommand extends Command
{
    protected $signature = 'crm:retry-touchpoints {--tenant=} {--limit=50}';

    protected $description = 'Reintenta touchpoints fallidos o retrasados que ya estan vencidos para la cola CRM';

    public function handle(
        CustomerAutomationManager $automationManager,
        CustomerTouchpointGovernance $governance,
    ): int {
        $tenantId = $this->option('tenant');
        $limit = max(1, (int) $this->option('limit'));

        $query = CustomerTouchpoint::query()
            ->withoutGlobalScopes()
            ->with(['customerProfile' => fn ($builder) => $builder->withoutGlobalScopes()->with('tenant')])
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->orderBy('next_retry_at')
            ->limit($limit);

        if (filled($tenantId)) {
            $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($tenantId);
            $query->where('tenant_id', (string) $tenant->getKey());
        }

        $processed = 0;
        $retried = 0;
        $pending = 0;

        $query->get()->each(function (CustomerTouchpoint $touchpoint) use ($automationManager, $governance, &$processed, &$retried, &$pending): void {
            $processed++;

            if (! $governance->canRetry($touchpoint)) {
                return;
            }

            $updated = $automationManager->retryTouchpoint($touchpoint);

            if ($updated->status === CustomerTouchpoint::STATUS_SENT) {
                $retried++;

                return;
            }

            if ($updated->next_retry_at !== null) {
                $pending++;
            }
        });

        $this->components->info("Reintentos procesados: {$processed}. Exitosos: {$retried}. Siguen pendientes: {$pending}.");

        return self::SUCCESS;
    }
}
