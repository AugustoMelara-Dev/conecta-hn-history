<?php

declare(strict_types=1);

namespace App\Infrastructure\Interactions;

use App\Application\Interactions\Contracts\LeadFollowUpPlaybookInterface;
use App\Domain\TenantGovernance\Services\OperationalPlaybookTaskManager;
use App\Models\Lead;
use App\Models\Tenant;

final readonly class EloquentLeadFollowUpPlaybook implements LeadFollowUpPlaybookInterface
{
    public function __construct(
        private OperationalPlaybookTaskManager $playbookTaskManager,
    ) {}

    public function sync(string $tenantId, int|string $leadId): void
    {
        $tenant = Tenant::query()->find($tenantId);

        // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
        $lead = Lead::withoutGlobalScopes()
            ->whereKey($leadId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $tenant instanceof Tenant || ! $lead instanceof Lead) {
            return;
        }

        $this->playbookTaskManager->syncForTrigger($tenant, 'lead_follow_up', [
            'related' => $lead,
            'customer_name' => (string) ($lead->name ?? 'este cliente'),
        ]);
    }
}
