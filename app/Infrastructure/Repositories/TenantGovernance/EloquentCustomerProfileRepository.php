<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\TenantGovernance;

use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\CustomerProfile;
use Illuminate\Support\Collection;

final class EloquentCustomerProfileRepository implements CustomerProfileRepositoryInterface
{
    public function findWithoutScopes(int|string|null $id): ?CustomerProfile
    {
        if ($id === null || $id === '') {
            return null;
        }

        return CustomerProfile::query()
            ->withoutGlobalScopes()
            ->find($id);
    }

    public function create(array $attributes): CustomerProfile
    {
        return CustomerProfile::query()
            ->withoutGlobalScopes()
            ->forceCreate($attributes);
    }

    public function save(CustomerProfile $profile): CustomerProfile
    {
        $profile->save();

        /** @var CustomerProfile $fresh */
        $fresh = $profile->fresh();

        return $fresh;
    }

    public function deleteWithoutScopes(int|string $id): void
    {
        CustomerProfile::query()
            ->withoutGlobalScopes()
            ->whereKey($id)
            ->delete();
    }

    public function findMatchingByContact(string $tenantId, ?string $normalizedEmail, ?string $normalizedPhone): Collection
    {
        return CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($normalizedEmail, $normalizedPhone): void {
                if ($normalizedEmail !== null) {
                    $query->where('normalized_email', $normalizedEmail);
                }

                if ($normalizedPhone !== null) {
                    if ($normalizedEmail !== null) {
                        $query->orWhere('normalized_phone', $normalizedPhone);
                    } else {
                        $query->where('normalized_phone', $normalizedPhone);
                    }
                }
            })
            ->orderByDesc('last_seen_at')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function searchDuplicates(string $tenantId, int|string $excludedId, string $search, int $limit = 20): Collection
    {
        $query = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $excludedId)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('updated_at')
            ->limit($limit);

        if (trim($search) !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        return $query->get();
    }

    public function findTenantRecord(string $tenantId, int|string $id): ?CustomerProfile
    {
        return CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);
    }
}
