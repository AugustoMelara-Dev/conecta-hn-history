<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface;
use App\Models\CustomerProfile;

final class CustomerProfileWorkspaceReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceCardsReader $cardsReader,
        private readonly CustomerProfileDuplicateReader $duplicateReader,
    ) {}

    /**
     * @return list<array{label:string,value:string,copy:string}>
     */
    public function workspaceCards(CustomerProfile $profile): array
    {
        return $this->cardsReader->cards($profile);
    }

    /**
     * @return array<int, string>
     */
    public function duplicateOptions(CustomerProfile $current, string $search): array
    {
        return $this->duplicateReader->options($current, $search);
    }

    public function duplicateLabel(CustomerProfile $current, mixed $value): ?string
    {
        return $this->duplicateReader->selectedLabel($current, $value);
    }
}
