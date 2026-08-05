<?php

declare(strict_types=1);

namespace App\Infrastructure\Broadcasting;

use App\Domain\Access\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TenantChannelAuthorizer
{
    public function canAccessTenant(User $user, string $tenantId): bool
    {
        if (DB::table('tenants')
            ->where('id', $tenantId)
            ->where('user_id', (string) $user->getKey())
            ->exists()) {
            return true;
        }

        return DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', (string) $user->getKey())
            ->where('status', 'active')
            ->whereNotNull('accepted_at')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->exists();
    }

    public function canAccessTenantAdmin(User $user, string $tenantId): bool
    {
        if (DB::table('tenants')
            ->where('id', $tenantId)
            ->where('user_id', (string) $user->getKey())
            ->exists()) {
            return true;
        }

        return DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', (string) $user->getKey())
            ->where('status', 'active')
            ->whereNotNull('accepted_at')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->whereIn('role', [Role::OWNER->value, Role::ADMIN->value])
            ->exists();
    }

    public function canAccessOrder(User $user, string $orderId): bool
    {
        $tenantId = DB::table('orders')->where('id', $orderId)->value('tenant_id');

        return is_string($tenantId) && $this->canAccessTenant($user, $tenantId);
    }
}
