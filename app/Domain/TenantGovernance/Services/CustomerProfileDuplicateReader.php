<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\CustomerProfile;

final class CustomerProfileDuplicateReader
{
    public function __construct(
        private readonly CustomerProfileRepositoryInterface $customerProfiles,
    ) {}

    /**
     * @return array<int, string>
     */
    public function options(CustomerProfile $current, string $search): array
    {
        return $this->customerProfiles
            ->searchDuplicates((string) $current->tenant_id, (string) $current->getKey(), $search)
            ->mapWithKeys(fn (CustomerProfile $profile): array => [(int) $profile->getKey() => $this->label($profile)])
            ->all();
    }

    public function selectedLabel(CustomerProfile $current, mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $profile = $this->customerProfiles->findTenantRecord((string) $current->tenant_id, (int) $value);

        return $profile instanceof CustomerProfile ? $this->label($profile) : null;
    }

    private function label(CustomerProfile $profile): string
    {
        return trim(implode(' | ', array_filter([
            '#'.$profile->getKey(),
            $profile->name,
            $profile->email,
            $profile->phone,
            $profile->last_seen_at?->diffForHumans(),
        ])));
    }
}
