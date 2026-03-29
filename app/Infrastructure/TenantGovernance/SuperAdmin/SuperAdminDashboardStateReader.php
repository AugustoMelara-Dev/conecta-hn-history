<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

use App\Models\OperationalPlaybookTask;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;

final class SuperAdminDashboardStateReader
{
    public function __construct(
        private readonly SuperAdminPortfolioReader $portfolioReader,
        private readonly SuperAdminDashboardCountsReader $countsReader,
        private readonly SuperAdminDashboardSnapshotStateReader $snapshotReader,
        private readonly SuperAdminDashboardRouteResolver $routeResolver,
    ) {}

    /**
     * @param  array<string, string>  $urls
     * @return array<string, mixed>
     */
    public function read(array $urls): array
    {
        /** @var list<OperationalPlaybookTask> $activePlaybookTasks */
        $activePlaybookTasks = $this->portfolioReader->activePlaybookTasks();
        /** @var list<Tenant> $portfolioTenants */
        $portfolioTenants = $this->portfolioReader->portfolioTenants();
        /** @var list<SubscriptionPayment> $monthlyProConversions */
        $monthlyProConversions = $this->portfolioReader->monthlyProConversions();

        $tasks = collect($activePlaybookTasks);
        $portfolio = collect($portfolioTenants);
        $conversions = collect($monthlyProConversions);
        $playbookCounts = $this->portfolioReader->playbookCounts($tasks);
        $metrics = $this->countsReader->read($portfolio, $conversions, $playbookCounts);

        return [
            'activePlaybookTasks' => $activePlaybookTasks,
            'portfolioTenants' => $portfolioTenants,
            'monthlyProConversions' => $monthlyProConversions,
            ...$metrics,
            'snapshot' => $this->snapshotReader->read($metrics, $tasks, $portfolio, $conversions, $urls, $this->routeResolver->callback()),
        ];
    }
}
