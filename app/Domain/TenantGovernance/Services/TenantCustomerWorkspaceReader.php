<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\HtmlString;

final class TenantCustomerWorkspaceReader
{
    public function __construct(
        private readonly CustomerProfileWorkspaceHtmlReader $workspaceHtmlReader,
        private readonly CustomerProfileTimelineHtmlReader $timelineHtmlReader,
    ) {}

    /**
     * @return array<string, string>
     */
    public function teamOptions(Tenant $tenant): array
    {
        $userIds = TenantUser::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->pluck('user_id')
            ->push((string) $tenant->user_id)
            ->filter()
            ->unique()
            ->values();

        return User::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $userIds->all())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function locationOptions(Tenant $tenant): array
    {
        return Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
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
