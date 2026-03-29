<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;
use Illuminate\Support\HtmlString;

final class CustomerProfileWorkspacePlaceholderReader
{
    public function __construct(
        private readonly CustomerProfileSectionViewReader $sectionViewReader,
    ) {}

    public function workspaceLabel(): string
    {
        return 'Workspace operativo';
    }

    public function timelineLabel(): string
    {
        return 'Timeline 360';
    }

    public function workspaceContent(?CustomerProfile $profile): HtmlString|string
    {
        return $this->sectionViewReader->workspaceHtml($profile);
    }

    public function timelineContent(?CustomerProfile $profile): HtmlString|string
    {
        return $this->sectionViewReader->timelineHtml($profile);
    }
}
