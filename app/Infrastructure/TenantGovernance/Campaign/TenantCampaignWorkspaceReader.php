<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\Campaign;

use App\Domain\Marketing\Enums\AudienceType;
use App\Domain\TenantGovernance\Services\BusinessGrowthBlueprint;
use App\Domain\TenantGovernance\Services\CustomerCampaignAudienceResolver;
use App\Domain\TenantGovernance\Services\CustomerLoyaltyManager;
use App\Models\Campaign;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\Waitlist;
use Illuminate\Support\HtmlString;

final class TenantCampaignWorkspaceReader
{
    public function __construct(
        private readonly CustomerCampaignAudienceResolver $audienceResolver,
        private readonly CustomerLoyaltyManager $loyaltyManager,
        private readonly BusinessGrowthBlueprint $growthBlueprint,
    ) {}

    public function calculateAudienceCount(Tenant $tenant, Campaign $campaign): int
    {
        if ($campaign->audience_type === AudienceType::CRM_CUSTOMERS) {
            return $this->audienceResolver->countForCampaign($tenant, $campaign);
        }

        return match ($campaign->audience_type) {
            AudienceType::FOLLOWERS, null => $tenant->followers()->count(),
            AudienceType::LEADS => \App\Models\Lead::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $tenant->getKey())
                ->where(function ($query): void {
                    $query->whereNotNull('email')->orWhereNotNull('phone');
                })
                ->count(),
            AudienceType::ALL_CUSTOMERS => \App\Models\Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $tenant->getKey())
                ->where(function ($query): void {
                    $query->whereNotNull('customer_email')->orWhereNotNull('customer_phone');
                })
                ->selectRaw("coalesce(nullif(lower(customer_email), ''), concat('phone:', nullif(customer_phone, '')), concat('appointment:', id)) as customer_key")
                ->groupBy('customer_key')
                ->count(),
            AudienceType::WAITLIST => Waitlist::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $tenant->getKey())
                ->where(function ($query): void {
                    $query->whereNotNull('email')->orWhereNotNull('phone');
                })
                ->count(),
        };
    }

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

    /**
     * @return array<string, string>
     */
    public function rewardOptions(Tenant $tenant): array
    {
        return collect($this->loyaltyManager->rewardCampaignOptionsForTenant($tenant))
            ->mapWithKeys(fn (string $label, int|string $key): array => [(string) $key => $label])
            ->all();
    }

    public function cadencePlaybooksHint(Tenant $tenant): HtmlString|string
    {
        /** @var array<int, array<string, mixed>> $playbooks */
        $playbooks = collect((array) ($this->growthBlueprint->forTenant($tenant)['cadence_playbooks'] ?? []))
            ->take(3)
            ->all();

        if ($playbooks === []) {
            return 'Todavia no hay playbooks de cadence sugeridos para este rubro.';
        }

        $items = collect($playbooks)->map(function (array $playbook): string {
            $title = (string) ($playbook['title'] ?? 'Cadencia sugerida');
            $cadence = (string) ($playbook['cadence'] ?? 'Manual');
            $copy = (string) ($playbook['copy'] ?? '');
            $trigger = (string) ($playbook['trigger'] ?? '');

            return '<li><strong>'.$title.'</strong> · '.$cadence.'<br><span>'.$copy.'</span><br><small>Disparo: '.$trigger.'</small></li>';
        })->implode('');

        return new HtmlString('<ul style="margin:0; padding-left:1rem; display:grid; gap:0.75rem;">'.$items.'</ul>');
    }
}
