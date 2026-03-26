<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardDefaultsReader
{
    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        return [
            'usersCount' => 0,
            'pendingPaymentsCount' => 0,
            'recentPayments' => [],
            'activePlaybookCount' => 0,
            'overduePlaybookCount' => 0,
            'unassignedPlaybookCount' => 0,
            'commercialFollowupCount' => 0,
            'renewalPlaybookCount' => 0,
            'activationPlaybookCount' => 0,
            'growthPlaybookCount' => 0,
            'tenantsCount' => 0,
            'proTenantsCount' => 0,
            'proConversionsThisMonthCount' => 0,
            'pendingTenantsCount' => 0,
            'suspendedTenantsCount' => 0,
            'atRiskTenantsCount' => 0,
            'catalogGapCount' => 0,
            'expiringProCount' => 0,
            'crmWatchlistCount' => 0,
            'crmOverdueAccountsCount' => 0,
            'crmOwnedAccountsCount' => 0,
            'crmFollowupsTodayCount' => 0,
            'openAttentionCount' => 0,
            'attentionQueues' => [],
            'operationalLanes' => [],
            'globalWorkboard' => [],
            'assigneeLoad' => [],
            'ownerPortfolio' => [],
            'teamPortfolio' => [],
            'slaBacklog' => [],
            'commercialFunnel' => [],
            'ownerGoals' => [],
            'teamGoals' => [],
            'verticalMonthlyGoals' => [],
            'activationCohorts' => [],
            'planCohorts' => [],
            'cityCohorts' => [],
            'channelCohorts' => [],
            'ownerCohorts' => [],
            'segmentExpansion' => [],
            'verticalChurn' => [],
            'verticalJourneyUplift' => [],
            'revenuePlaybooks' => [],
            'networkJourneyPaths' => [],
            'churnWatchlist' => [],
            'commercialFollowups' => [],
        ];
    }
}
