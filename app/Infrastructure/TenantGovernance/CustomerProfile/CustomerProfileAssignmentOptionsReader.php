<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

final class CustomerProfileAssignmentOptionsReader
{
    /**
     * @return array<string, string>
     */
    public function teamOptions(Tenant $tenant): array
    {
        $userIds = TenantUser::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->pluck('user_id')
            ->push((string) $tenant->user_id)
            ->filter()
            ->unique()
            ->values();

        return User::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $userIds->all())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function locationOptions(Tenant $tenant): array
    {
        return Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }
}
