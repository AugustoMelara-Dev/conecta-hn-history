<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardViewStateReader
{
    public function __construct(
        private readonly SuperAdminDashboardStateReader $stateReader,
    ) {}

    /**
     * @param  array<string, string>  $urls
     * @return array<string, mixed>
     */
    public function read(array $urls, callable $safeRoute): array
    {
        $state = $this->stateReader->read($urls, $safeRoute);
        $snapshot = (array) ($state['snapshot'] ?? []);

        return array_replace($this->defaults(), [
            'usersCount' => (int) ($state['usersCount'] ?? 0),
            'pendingPaymentsCount' => (int) ($state['pendingPaymentsCount'] ?? 0),
            'recentPayments' => (array) ($state['recentPayments'] ?? []),
            'activePlaybookCount' => (int) ($state['activePlaybookCount'] ?? 0),
            'overduePlaybookCount' => (int) ($state['overduePlaybookCount'] ?? 0),
            'unassignedPlaybookCount' => (int) ($state['unassignedPlaybookCount'] ?? 0),
            'commercialFollowupCount' => (int) ($state['commercialFollowupCount'] ?? 0),
            'renewalPlaybookCount' => (int) ($state['renewalPlaybookCount'] ?? 0),
            'activationPlaybookCount' => (int) ($state['activationPlaybookCount'] ?? 0),
            'growthPlaybookCount' => (int) ($state['growthPlaybookCount'] ?? 0),
            'tenantsCount' => (int) ($state['tenantsCount'] ?? 0),
            'proTenantsCount' => (int) ($state['proTenantsCount'] ?? 0),
            'proConversionsThisMonthCount' => (int) ($state['proConversionsThisMonthCount'] ?? 0),
            'pendingTenantsCount' => (int) ($state['pendingTenantsCount'] ?? 0),
            'suspendedTenantsCount' => (int) ($state['suspendedTenantsCount'] ?? 0),
            'atRiskTenantsCount' => (int) ($state['atRiskTenantsCount'] ?? 0),
            'catalogGapCount' => (int) ($state['catalogGapCount'] ?? 0),
            'expiringProCount' => (int) ($state['expiringProCount'] ?? 0),
            'crmWatchlistCount' => (int) ($state['crmWatchlistCount'] ?? 0),
            'crmOverdueAccountsCount' => (int) ($state['crmOverdueAccountsCount'] ?? 0),
            'crmOwnedAccountsCount' => (int) ($state['crmOwnedAccountsCount'] ?? 0),
            'crmFollowupsTodayCount' => (int) ($state['crmFollowupsTodayCount'] ?? 0),
            'openAttentionCount' => (int) ($snapshot['openAttentionCount'] ?? 0),
            'attentionQueues' => (array) ($snapshot['attentionQueues'] ?? []),
            'operationalLanes' => (array) ($snapshot['operationalLanes'] ?? []),
            'globalWorkboard' => (array) ($snapshot['globalWorkboard'] ?? []),
            'assigneeLoad' => (array) ($snapshot['assigneeLoad'] ?? []),
            'ownerPortfolio' => (array) ($snapshot['ownerPortfolio'] ?? []),
            'teamPortfolio' => (array) ($snapshot['teamPortfolio'] ?? []),
            'slaBacklog' => (array) ($snapshot['slaBacklog'] ?? []),
            'commercialFunnel' => (array) ($snapshot['commercialFunnel'] ?? []),
            'ownerGoals' => (array) ($snapshot['ownerGoals'] ?? []),
            'teamGoals' => (array) ($snapshot['teamGoals'] ?? []),
            'verticalMonthlyGoals' => (array) ($snapshot['verticalMonthlyGoals'] ?? []),
            'activationCohorts' => (array) ($snapshot['activationCohorts'] ?? []),
            'planCohorts' => (array) ($snapshot['planCohorts'] ?? []),
            'cityCohorts' => (array) ($snapshot['cityCohorts'] ?? []),
            'channelCohorts' => (array) ($snapshot['channelCohorts'] ?? []),
            'ownerCohorts' => (array) ($snapshot['ownerCohorts'] ?? []),
            'segmentExpansion' => (array) ($snapshot['segmentExpansion'] ?? []),
            'verticalChurn' => (array) ($snapshot['verticalChurn'] ?? []),
            'verticalJourneyUplift' => (array) ($snapshot['verticalJourneyUplift'] ?? []),
            'revenuePlaybooks' => (array) ($snapshot['revenuePlaybooks'] ?? []),
            'networkJourneyPaths' => (array) ($snapshot['networkJourneyPaths'] ?? []),
            'churnWatchlist' => (array) ($snapshot['churnWatchlist'] ?? []),
            'commercialFollowups' => (array) ($snapshot['commercialFollowups'] ?? []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
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
