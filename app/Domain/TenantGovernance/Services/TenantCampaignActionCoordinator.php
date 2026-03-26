<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Application\Marketing\Jobs\DispatchCampaignSegment;
use App\Models\Campaign;
use App\Models\Tenant;

final class TenantCampaignActionCoordinator
{
    public function __construct(
        private readonly TenantCampaignDispatchPlanner $dispatchPlanner,
    ) {}

    /**
     * @return array{status:string,title:string,body:string}
     */
    public function dispatch(Tenant $tenant, Campaign $campaign): array
    {
        $dispatch = $this->dispatchPlanner->planDispatch($tenant, $campaign);
        $audienceCount = (int) ($dispatch['audience_count'] ?? 0);

        if ($audienceCount === 0) {
            return [
                'status' => 'warning',
                'title' => 'Audiencia vacia',
                'body' => 'No hay contactos elegibles con estos filtros y opt-in.',
            ];
        }

        $this->dispatchPlanner->queueCampaign($campaign, $dispatch['next_run_at']);
        DispatchCampaignSegment::dispatch($campaign->id, (string) $tenant->getKey());

        return [
            'status' => 'success',
            'title' => (bool) ($dispatch['uses_cadence'] ?? false) ? 'Cadencia activada' : 'Campana en cola',
            'body' => (bool) ($dispatch['uses_cadence'] ?? false)
                ? "La campana se lanzo ahora y seguira viva para {$audienceCount} destinatarios elegibles."
                : "Se procesara el envio a {$audienceCount} destinatarios en breve.",
        ];
    }
}
