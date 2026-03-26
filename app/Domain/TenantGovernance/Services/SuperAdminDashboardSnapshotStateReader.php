<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\OperationalPlaybookTask;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class SuperAdminDashboardSnapshotStateReader
{
    public function __construct(
        private readonly SuperAdminDashboardAssembler $assembler,
    ) {}

    /**
     * @param  array<string, int>  $metrics
     * @param  Collection<int, OperationalPlaybookTask>  $tasks
     * @param  Collection<int, Tenant>  $portfolio
     * @param  Collection<int, SubscriptionPayment>  $monthlyProConversions
     * @param  array<string, string>  $urls
     * @return array<string, mixed>
     */
    public function read(
        array $metrics,
        Collection $tasks,
        Collection $portfolio,
        Collection $monthlyProConversions,
        array $urls,
    ): array {
        return $this->assembler->build($metrics, $tasks, $portfolio, $monthlyProConversions, $urls);
    }
}
