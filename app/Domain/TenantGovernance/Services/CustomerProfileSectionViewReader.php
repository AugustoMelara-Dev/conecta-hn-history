<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;
use Illuminate\Support\HtmlString;

final class CustomerProfileSectionViewReader
{
    public function __construct(
        private readonly TenantCustomerWorkspaceReader $workspaceReader,
    ) {}

    public function workspaceHtml(?CustomerProfile $profile): HtmlString|string
    {
        return $this->workspaceReader->workspaceHtml($profile);
    }

    public function timelineHtml(?CustomerProfile $profile): HtmlString|string
    {
        return $this->workspaceReader->timelineHtml($profile);
    }
}
