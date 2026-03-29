<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;
use Illuminate\Support\HtmlString;

final class CustomerProfileSectionViewReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceSectionReader $workspaceSectionReader,
        private readonly CustomerProfileTimelineSectionViewReader $timelineSectionReader,
    ) {}

    public function workspaceHtml(?CustomerProfile $profile): HtmlString|string
    {
        return $this->workspaceSectionReader->render($profile);
    }

    public function timelineHtml(?CustomerProfile $profile): HtmlString|string
    {
        return $this->timelineSectionReader->render($profile);
    }
}
