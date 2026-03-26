<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\OperationalPlaybookTask;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class SuperAdminDashboardStateReader
{
    public function __construct(
        private readonly SuperAdminPortfolioReader $portfolioReader,
        private readonly SuperAdminDashboardAssembler $assembler,
    ) {}

    /**
     * @param  array<string, string>  $urls
     * @return array<string, mixed>
     */
    public function read(array $urls, callable $safeRoute): array
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
        $metrics = $this->portfolioReader->metrics($portfolio, $conversions);

        return [
            'activePlaybookTasks' => $activePlaybookTasks,
            'portfolioTenants' => $portfolioTenants,
            'monthlyProConversions' => $monthlyProConversions,
            'usersCount' => $this->portfolioReader->usersCount(),
            'pendingPaymentsCount' => $this->portfolioReader->pendingPaymentsCount(),
            'recentPayments' => $this->portfolioReader->recentPayments(),
            ...$playbookCounts,
            ...$metrics,
            'snapshot' => $this->assembler->build(
                [
                    ...$metrics,
                    ...$playbookCounts,
                ],
                $tasks,
                $portfolio,
                $conversions,
                $urls,
                $safeRoute,
            ),
        ];
    }
}
