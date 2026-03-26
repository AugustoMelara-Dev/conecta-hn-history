<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerTouchpoint;

final class CustomerTouchpointConversationSynchronizer
{
    public function __construct(
        private readonly CustomerTouchpointOutcomeSynchronizer $outcomeSynchronizer,
    ) {}

    public function sync(CustomerTouchpoint $touchpoint): void
    {
        $this->outcomeSynchronizer->sync($touchpoint);
    }
}
