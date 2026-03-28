<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class SuperAdminDashboardCountsReader
{
    public function __construct(
        private readonly SuperAdminPortfolioReader $portfolioReader,
    ) {}

    /**
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, SubscriptionPayment>  $conversions
     * @param  array<string, int>  $playbookCounts
     * @return array<string, mixed>
     */
    public function read(Collection $portfolio, Collection $conversions, array $playbookCounts): array
    {
        return [
            'usersCount' => $this->portfolioReader->usersCount(),
            'pendingPaymentsCount' => $this->portfolioReader->pendingPaymentsCount(),
            'recentPayments' => $this->portfolioReader->recentPayments(),
            ...$playbookCounts,
            ...$this->portfolioReader->metrics($portfolio, $conversions),
        ];
    }
}
