<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Tenant\Contracts\DashboardMetricsRepository;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DashboardController - Humble Object
 *
 * "Zero-Latency" & Logic-Free.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DashboardMetricsRepository $metricsRepository
    ) {}

    public function index(): Response
    {
        $tenantId = $this->tenantContext->getTenantId();

        // Orchestration Only
        return Inertia::render('Tenant/Dashboard', [
            'stats' => $this->metricsRepository->getKpiSummary($tenantId),
            'onboarding' => $this->metricsRepository->getOnboardingStatus($tenantId),
            'latestOrders' => $this->metricsRepository->getLatestOrders($tenantId),
            // Trend data is optional/lazy loaded usually, but including here if spec requires.
            // Spec allows passing "crude data".
        ]);
    }
}
