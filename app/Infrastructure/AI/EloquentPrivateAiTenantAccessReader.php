<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\PrivateAiTenantAccessReaderInterface;
use App\Models\Tenant;

final class EloquentPrivateAiTenantAccessReader implements PrivateAiTenantAccessReaderInterface
{
    public function findAllowlistedTenant(string $tenantId): ?object
    {
        // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
        $tenant = Tenant::withoutGlobalScopes()->find((string) $tenantId);

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $allowlist = array_merge(
            (array) config('ai.private.tenant_allowlist', []),
            (array) config('conecta.features.tenant_private_agent_tenants', []),
        );

        $allowlist = array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $allowlist,
        );

        if ($allowlist === []) {
            return null;
        }

        $allowed = in_array(strtolower((string) $tenant->id), $allowlist, true);

        return $allowed ? $tenant : null;
    }
}
