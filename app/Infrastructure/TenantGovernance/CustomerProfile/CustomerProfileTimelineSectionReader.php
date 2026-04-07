<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;

final class CustomerProfileTimelineSectionReader
{
    public function __construct(
        private readonly CustomerProfileTimelineBuilder $timelineBuilder,
    ) {}

    /**
     * @return list<array{type:string,title:string,copy:string,channel:?string,when:string,color:string}>
     */
    public function items(CustomerProfile $profile, int $limit = 18): array
    {
        return $this->timelineBuilder->build($profile, $limit);
    }
}
