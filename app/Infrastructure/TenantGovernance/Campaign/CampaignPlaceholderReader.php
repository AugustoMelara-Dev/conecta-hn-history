<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Campaign;

use App\Models\Campaign;
use App\Models\Tenant;
use Illuminate\Support\HtmlString;

final class CampaignPlaceholderReader
{
    public function __construct(
        private readonly TenantCampaignPresentationReader $presentationReader,
        private readonly TenantCampaignWorkspaceReader $workspaceReader,
    ) {}

    public function cadencePlaybooksHint(?Tenant $tenant): HtmlString|string
    {
        if (! $tenant instanceof Tenant) {
            return 'Selecciona un tenant valido para cargar recomendaciones de cadence.';
        }

        return $this->workspaceReader->cadencePlaybooksHint($tenant);
    }

    public function recipientsCopy(?Campaign $campaign): string
    {
        return $this->presentationReader->recipientsCopy($campaign);
    }

    public function sentAtCopy(?Campaign $campaign): string
    {
        return $this->presentationReader->sentAtCopy($campaign);
    }

    public function nextRunCopy(?Campaign $campaign): string
    {
        return $this->presentationReader->nextRunCopy($campaign);
    }
}
