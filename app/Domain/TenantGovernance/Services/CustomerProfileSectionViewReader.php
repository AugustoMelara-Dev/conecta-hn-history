<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;
use Illuminate\Support\HtmlString;

final class CustomerProfileSectionViewReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceHtmlReader $workspaceHtmlReader,
        private readonly CustomerProfileTimelineHtmlReader $timelineHtmlReader,
    ) {}

    public function workspaceHtml(?CustomerProfile $profile): HtmlString|string
    {
        return $this->workspaceHtmlReader->render($profile);
    }

    public function timelineHtml(?CustomerProfile $profile): HtmlString|string
    {
        return $this->timelineHtmlReader->render($profile);
    }
}
