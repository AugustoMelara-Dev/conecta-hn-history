<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

final class SuperAdminDashboardSnapshotReader
{
    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function read(array $state): array
    {
        $snapshot = (array) ($state['snapshot'] ?? []);

        return [
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
        ];
    }
}
