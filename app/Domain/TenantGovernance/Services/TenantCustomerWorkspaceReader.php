<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;
use App\Models\Tenant;
use Illuminate\Support\HtmlString;

final class TenantCustomerWorkspaceReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceHtmlReader $workspaceHtmlReader,
        private readonly CustomerProfileTimelineHtmlReader $timelineHtmlReader,
        private readonly CustomerProfileAssignmentOptionsReader $assignmentOptionsReader,
    ) {}

    /**
     * @return array<string, string>
     */
    public function teamOptions(Tenant $tenant): array
    {
        return $this->assignmentOptionsReader->teamOptions($tenant);
    }

    /**
     * @return array<string, string>
     */
    public function locationOptions(Tenant $tenant): array
    {
        return $this->assignmentOptionsReader->locationOptions($tenant);
    }

    public function workspaceHtml(?CustomerProfile $profile): HtmlString|string
    {
        return $this->workspaceHtmlReader->render($profile);
    }

    public function timelineHtml(?CustomerProfile $profile): HtmlString|string
    {
        return $this->timelineHtmlReader->render($profile);
    }
}
