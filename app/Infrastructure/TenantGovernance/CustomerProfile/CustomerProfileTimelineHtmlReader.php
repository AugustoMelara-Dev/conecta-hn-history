<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;
use Illuminate\Support\HtmlString;

final class CustomerProfileTimelineHtmlReader
{
    public function __construct(
        private readonly CustomerProfileTimelineStateReader $timelineStateReader,
        private readonly CustomerProfileTimelineItemsHtmlReader $itemsHtmlReader,
    ) {}

    public function render(?CustomerProfile $profile): HtmlString|string
    {
        $state = $this->timelineStateReader->read($profile);
        $items = $state['items'];

        if ($items === []) {
            return $state['empty'];
        }

        return $this->itemsHtmlReader->render($items);
    }
}
