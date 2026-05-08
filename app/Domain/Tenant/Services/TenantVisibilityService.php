<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Services;

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
    public function check(object $tenant): void
    {
        $attributes = method_exists($tenant, 'getAttributes') ? $tenant->getAttributes() : [];

        if (! ($attributes['is_active'] ?? $tenant->is_active ?? false)) {
            abort(404, 'Este negocio no esta disponible.');
        }

        $subscriptionStatus = $attributes['subscription_status'] ?? 'active';

        if (! in_array($subscriptionStatus, ['active', 'trial'], true)) {
            abort(404, 'Este negocio no esta disponible.');
        }
    }
}
