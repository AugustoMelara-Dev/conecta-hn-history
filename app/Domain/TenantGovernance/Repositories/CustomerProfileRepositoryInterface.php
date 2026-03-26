<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Repositories;

use App\Models\CustomerProfile;
use Illuminate\Support\Collection;

interface CustomerProfileRepositoryInterface
{
    public function findWithoutScopes(int|string|null $id): ?CustomerProfile;

    public function create(array $attributes): CustomerProfile;

    public function save(CustomerProfile $profile): CustomerProfile;

    public function deleteWithoutScopes(int|string $id): void;

    /**
     * @return Collection<int, CustomerProfile>
     */
    public function findMatchingByContact(string $tenantId, ?string $normalizedEmail, ?string $normalizedPhone): Collection;

    /**
     * @return Collection<int, CustomerProfile>
     */
    public function searchDuplicates(string $tenantId, int|string $excludedId, string $search, int $limit = 20): Collection;

    public function findTenantRecord(string $tenantId, int|string $id): ?CustomerProfile;
}
