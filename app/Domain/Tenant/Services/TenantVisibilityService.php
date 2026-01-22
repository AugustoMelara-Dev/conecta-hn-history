<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Services;

use App\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * TenantVisibilityService - Gatekeeper Logic
 */
final class TenantVisibilityService
{
    /**
     * Ensure tenant is visible to public visitors.
     *
     * @throws NotFoundHttpException
     */
    public function check(Tenant $tenant): void
    {
        // Layer 1: Administrative approval
        if (! $tenant->is_active) {
            abort(404, 'Este negocio no está disponible.');
        }

        // Layer 2: Subscription status (no morosos in public directory)
        if (! in_array($tenant->subscription_status, ['active', 'trial'], true)) {
            abort(404, 'Este negocio no está disponible.');
        }
    }
}
