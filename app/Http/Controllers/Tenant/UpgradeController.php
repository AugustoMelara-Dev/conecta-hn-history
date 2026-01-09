<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UpgradeController - Configuration Agnostic
 *
 * ARCHITECTURE:
 * - "Humble Object": Only transports config data to view.
 * - Single Responsibility: Display upgrade options.
 * - No HARDCODED DATA.
 */
final class UpgradeController extends Controller
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly TenantContext $tenantContext
    ) {}

    public function index(): Response
    {
        // 1. Resolve Context
        $tenantId = $this->tenantContext->getTenantId();

        // 2. Fetch Config (Zero Logic)
        $plans = $this->config->get('subscriptions.plans', []);
        $methods = $this->config->get('subscriptions.payment_methods', []);

        // 3. Render View
        // Note: Passing empty tenant context or relying on shared props. 
        // Controller remains clean.
        return Inertia::render('Tenant/Upgrade', [
            'plans' => $plans,
            'paymentMethods' => $methods,
        ]);
    }
}
