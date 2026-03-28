<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

final class SuperAdminDashboardScalarStateReader
{
    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function read(array $state): array
    {
        return [
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
        ];
    }
}
