<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Marketing\Enums\AudienceType;
use App\Domain\TenantGovernance\Services\CustomerCampaignAudienceResolver;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class CustomerCampaignAudienceResolverTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_filters_crm_campaign_audience_by_segment_loyalty_owner_and_location(): void
    {
        [$owner, $tenant] = $this->makeTenantOwner('crm-campaign-audience');
        $salesUser = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $north = Location::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Sucursal Norte',
            'slug' => 'norte',
            'is_active' => true,
        ]);

        $south = Location::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Sucursal Sur',
            'slug' => 'sur',
            'is_active' => true,
        ]);

        $target = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $salesUser->getKey(),
            'location_id' => $north->getKey(),
            'name' => 'Ana VIP',
            'email' => 'ana@example.com',
            'phone' => '9999-1111',
            'customer_segment' => 'repeat',
            'loyalty_tier' => 'gold',
            'redeemable_rewards_count' => 1,
            'loyalty_points_balance' => 140,
            'marketing_opt_in' => true,
            'email_opt_in' => true,
        ]);

        CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $salesUser->getKey(),
            'location_id' => $south->getKey(),
            'name' => 'Ana Sur',
            'email' => 'anasur@example.com',
            'phone' => '9999-2222',
            'customer_segment' => 'repeat',
            'loyalty_tier' => 'gold',
            'redeemable_rewards_count' => 1,
            'loyalty_points_balance' => 140,
            'marketing_opt_in' => true,
            'email_opt_in' => true,
        ]);

        CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'assigned_user_id' => $owner->getKey(),
            'location_id' => $north->getKey(),
            'name' => 'Luis sin optin',
            'email' => 'luis@example.com',
            'phone' => '9999-3333',
            'customer_segment' => 'repeat',
            'loyalty_tier' => 'gold',
            'redeemable_rewards_count' => 1,
            'loyalty_points_balance' => 140,
            'marketing_opt_in' => false,
            'email_opt_in' => true,
        ]);

        $campaign = Campaign::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'subject' => 'VIP marzo',
            'content' => 'Queremos verte de vuelta.',
            'type' => 'email',
            'audience_type' => AudienceType::CRM_CUSTOMERS,
            'customer_segment' => 'repeat',
            'loyalty_tier' => 'gold',
            'only_redeemable_rewards' => true,
            'location_id' => $north->getKey(),
            'owner_user_id' => $salesUser->getKey(),
            'requires_marketing_opt_in' => true,
            'status' => 'draft',
            'recipients_count' => 0,
        ]);

        $resolver = app(CustomerCampaignAudienceResolver::class);

        $this->assertSame(1, $resolver->countForCampaign($tenant, $campaign));
        $this->assertSame([(int) $target->getKey()], $resolver->profileIdsForCampaign($tenant, $campaign));
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(string $slug): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $city = City::factory()->create();
        $category = Category::factory()->create();

        $tenant = Tenant::factory()->state([
            'user_id' => $user->id,
            'slug' => $slug,
            'business_type' => 'retail',
            'city_id' => $city->getKey(),
            'category_id' => $category->getKey(),
        ])->create();

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }
}
