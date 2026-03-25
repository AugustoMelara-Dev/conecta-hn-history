<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Services\CustomerLoyaltyManager;
use App\Models\Category;
use App\Models\City;
use App\Models\CustomerLoyaltyLedger;
use App\Models\CustomerProfile;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class CustomerLoyaltyManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_syncs_points_bonus_and_reward_redemption_for_customer_profiles(): void
    {
        [$user, $tenant] = $this->makeTenantOwner('crm-loyalty-norte');

        LoyaltyProgram::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Club Norte',
            'earn_ratio' => 10,
            'redemption_ratio' => 100,
            'expiry_days' => 365,
            'is_active' => true,
        ]);

        $rewardBasic = LoyaltyReward::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Cafe de cortesia',
            'description' => 'Beneficio simple para recompra',
            'points_cost' => 80,
            'stock' => -1,
            'is_active' => true,
        ]);

        $rewardPremium = LoyaltyReward::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Servicio premium',
            'description' => 'Canje de alto valor',
            'points_cost' => 120,
            'stock' => 3,
            'is_active' => true,
        ]);

        $profile = CustomerProfile::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Ana Castillo',
            'email' => 'ana@example.com',
            'phone' => '9999-1111',
            'preferred_channel' => 'whatsapp',
            'customer_segment' => 'repeat',
            'lifecycle_stage' => 'active',
        ]);

        Order::factory()
            ->forTenant($tenant)
            ->delivered()
            ->create([
                'user_id' => $user->getKey(),
                'customer_profile_id' => $profile->getKey(),
                'customer_name' => 'Ana Castillo',
                'customer_email' => 'ana@example.com',
                'customer_phone' => '9999-1111',
                'total' => 840.00,
                'subtotal' => 840.00,
                'discount' => 0.00,
                'delivery_fee' => 0.00,
            ]);

        $manager = app(CustomerLoyaltyManager::class);
        $manager->syncProfile($profile);

        $profile->refresh();

        $this->assertSame(84, $profile->loyalty_points_balance);
        $this->assertSame(84, $profile->lifetime_points_earned);
        $this->assertSame(1, $profile->redeemable_rewards_count);
        $this->assertSame('Cafe de cortesia', $profile->next_reward_name);
        $this->assertSame(36, $profile->points_to_next_reward);

        $manager->awardBonus($profile, 40, 'Cierre rapido de seguimiento', (string) $user->getKey());

        $profile->refresh();

        $this->assertSame(124, $profile->loyalty_points_balance);
        $this->assertSame(124, $profile->lifetime_points_earned);
        $this->assertSame(2, $profile->redeemable_rewards_count);
        $this->assertSame('Servicio premium', $profile->next_reward_name);
        $this->assertSame(0, $profile->points_to_next_reward);
        $this->assertSame([
            $rewardBasic->getKey() => 'Cafe de cortesia - 80 pts',
            $rewardPremium->getKey() => 'Servicio premium - 120 pts',
        ], $manager->rewardOptionsForProfile($profile));
        $this->assertSame([
            $rewardBasic->getKey() => 'Cafe de cortesia - 80 pts',
            $rewardPremium->getKey() => 'Servicio premium - 120 pts',
        ], $manager->rewardCampaignOptionsForTenant($tenant));
        $this->assertSame([
            'headline' => 'Reward listo: Servicio premium.',
            'copy' => 'Ya puedes canjearlo por 120 puntos.',
            'cta_label' => 'Canjear reward',
        ], $manager->rewardCalloutForProfile($profile->fresh(), $rewardPremium));

        $manager->redeemReward($profile, $rewardPremium, (string) $user->getKey());

        $profile->refresh();
        $rewardPremium->refresh();

        $this->assertSame(4, $profile->loyalty_points_balance);
        $this->assertSame(124, $profile->lifetime_points_earned);
        $this->assertSame(0, $profile->redeemable_rewards_count);
        $this->assertSame('Cafe de cortesia', $profile->next_reward_name);
        $this->assertSame(76, $profile->points_to_next_reward);
        $this->assertSame(2, $rewardPremium->stock);
        $this->assertSame(3, CustomerLoyaltyLedger::query()->withoutGlobalScopes()->count());
        $this->assertSame([], $manager->rewardOptionsForProfile($profile));
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
