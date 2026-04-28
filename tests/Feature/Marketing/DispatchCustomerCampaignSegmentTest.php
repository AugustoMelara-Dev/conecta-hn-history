<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Application\Marketing\Jobs\DispatchCampaignSegment;
use App\Application\Marketing\Jobs\SendCustomerCampaignTouchpoint;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Marketing\Enums\AudienceType;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerProfile;
use App\Models\CustomerTouchpoint;
use App\Models\LoyaltyReward;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class DispatchCustomerCampaignSegmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fans_out_crm_campaigns_to_customer_touchpoint_jobs(): void
    {
        Bus::fake();

        [$owner, $tenant] = $this->makeTenantOwner('crm-campaign-dispatch');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Ana CRM',
            'email' => 'ana@example.com',
            'customer_segment' => 'repeat',
            'marketing_opt_in' => true,
            'email_opt_in' => true,
            'assigned_user_id' => $owner->getKey(),
        ]);

        $campaign = Campaign::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'subject' => 'Reactiva abril',
            'content' => 'Tenemos una nueva oferta para ti.',
            'type' => 'email',
            'audience_type' => AudienceType::CRM_CUSTOMERS,
            'customer_segment' => 'repeat',
            'owner_user_id' => $owner->getKey(),
            'requires_marketing_opt_in' => true,
            'status' => 'draft',
            'recipients_count' => 0,
        ]);

        $job = new DispatchCampaignSegment($campaign->id, (string) $tenant->getKey());
        $job->handle(app(\App\Domain\TenantGovernance\Services\CustomerCampaignAudienceResolver::class));

        Bus::assertDispatched(SendCustomerCampaignTouchpoint::class, function (SendCustomerCampaignTouchpoint $job) use ($tenant, $campaign, $profile): bool {
            return $job->tenantId === (string) $tenant->getKey()
                && $job->campaignId === (int) $campaign->getKey()
                && $job->customerProfileId === (int) $profile->getKey();
        });

        $campaign->refresh();

        $this->assertSame('sending', $campaign->status);
        $this->assertSame(1, $campaign->recipients_count);
        $this->assertNotNull($campaign->sent_at);
    }

    public function test_it_sends_an_email_campaign_to_a_crm_profile_and_logs_touchpoint(): void
    {
        Mail::fake();

        [$owner, $tenant] = $this->makeTenantOwner('crm-campaign-send');

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Ana CRM',
            'email' => 'ana@example.com',
            'customer_segment' => 'repeat',
            'marketing_opt_in' => true,
            'email_opt_in' => true,
            'loyalty_points_balance' => 140,
        ]);

        $reward = LoyaltyReward::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Bundle premium',
            'description' => 'Upgrade con accesorios.',
            'points_cost' => 120,
            'stock' => -1,
            'is_active' => true,
        ]);

        $campaign = Campaign::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'subject' => 'Oferta VIP',
            'content' => 'Queremos verte de vuelta esta semana.',
            'type' => 'email',
            'audience_type' => AudienceType::CRM_CUSTOMERS,
            'owner_user_id' => $owner->getKey(),
            'loyalty_reward_id' => $reward->getKey(),
            'requires_marketing_opt_in' => true,
            'is_recurring' => true,
            'cadence_interval_days' => 7,
            'status' => 'sending',
            'recipients_count' => 1,
        ]);

        $job = new SendCustomerCampaignTouchpoint((string) $tenant->getKey(), (int) $campaign->getKey(), (int) $profile->getKey());
        $job->handle(
            app(\App\Domain\TenantGovernance\Services\CustomerAutomationManager::class),
            app(\App\Domain\TenantGovernance\Services\CustomerLoyaltyManager::class),
        );

        $touchpoint = CustomerTouchpoint::query()->withoutGlobalScopes()->latest('id')->first();

        $this->assertInstanceOf(CustomerTouchpoint::class, $touchpoint);
        $this->assertSame(CustomerTouchpoint::STATUS_SENT, $touchpoint->status);
        $this->assertSame('campaign_'.$campaign->id, $touchpoint->automation_key);
        $this->assertSame('ana@example.com', $touchpoint->recipient_address);
        $this->assertSame((string) $owner->getKey(), (string) $touchpoint->created_by_user_id);
        $this->assertSame($campaign->id, data_get($touchpoint->metadata, 'campaign_id'));
        $this->assertSame('Oferta VIP', data_get($touchpoint->metadata, 'campaign_subject'));
        $this->assertSame('Bundle premium', data_get($touchpoint->metadata, 'reward_name'));
        $this->assertSame(120, data_get($touchpoint->metadata, 'reward_points_cost'));
        $this->assertSame(7, data_get($touchpoint->metadata, 'cadence_interval_days'));
        $this->assertStringContainsString('Reward listo: Bundle premium.', (string) $touchpoint->message);
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
