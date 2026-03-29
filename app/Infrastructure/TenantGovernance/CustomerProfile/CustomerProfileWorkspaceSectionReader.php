<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Models\CustomerProfile;
use Illuminate\Support\HtmlString;

final class CustomerProfileWorkspaceSectionReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceHtmlReader $workspaceHtmlReader,
    ) {}

    public function render(?CustomerProfile $profile): HtmlString|string
    {
        return $this->workspaceHtmlReader->render($profile);
    }
}
