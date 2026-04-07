<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;
use Illuminate\Support\HtmlString;

final class CustomerProfileTimelineSectionViewReader
{
    public function __construct(
        private readonly CustomerProfileTimelineHtmlReader $timelineHtmlReader,
    ) {}

    public function render(?CustomerProfile $profile): HtmlString|string
    {
        return $this->timelineHtmlReader->render($profile);
    }
}
