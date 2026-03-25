<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\Marketing\Enums\AudienceType;
use App\Models\Campaign;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

class CustomerCampaignAudienceResolver
{
    public function queryForCampaign(Tenant|string $tenant, Campaign $campaign): Builder
    {
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : (string) $tenant;

        $query = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId);

        $campaign->loadMissing('loyaltyReward');

        if ($campaign->audience_type === AudienceType::CRM_CUSTOMERS || $campaign->customer_segment !== null || $campaign->loyalty_tier !== null || $campaign->loyalty_reward_id !== null) {
            if ($campaign->customer_segment !== null) {
                $query->whereRaw('coalesce(manual_segment, customer_segment) = ?', [$campaign->customer_segment]);
            }

            if ($campaign->loyalty_tier !== null) {
                $query->where('loyalty_tier', $campaign->loyalty_tier);
            }

            if ($campaign->only_redeemable_rewards) {
                if ($campaign->loyaltyReward?->points_cost !== null) {
                    $query->where('loyalty_points_balance', '>=', (int) $campaign->loyaltyReward->points_cost);
                } else {
                    $query->where('redeemable_rewards_count', '>', 0);
                }
            }

            if ($campaign->location_id !== null) {
                $query->where('location_id', (string) $campaign->location_id);
            }

            if ($campaign->owner_user_id !== null) {
                $query->where('assigned_user_id', (string) $campaign->owner_user_id);
            }
        }

        if ((bool) $campaign->requires_marketing_opt_in) {
            $query->where('marketing_opt_in', true);
        }

        return match ($campaign->type) {
            'whatsapp' => $query
                ->whereNotNull('phone')
                ->where('whatsapp_opt_in', true),
            default => $query
                ->whereNotNull('email')
                ->where('email_opt_in', true),
        };
    }

    public function countForCampaign(Tenant|string $tenant, Campaign $campaign): int
    {
        return $this->queryForCampaign($tenant, $campaign)->count();
    }

    /**
     * @return list<int>
     */
    public function profileIdsForCampaign(Tenant|string $tenant, Campaign $campaign): array
    {
        return $this->queryForCampaign($tenant, $campaign)
            ->orderByDesc('reactivation_score')
            ->orderByDesc('total_spent')
            ->pluck('id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();
    }
}


