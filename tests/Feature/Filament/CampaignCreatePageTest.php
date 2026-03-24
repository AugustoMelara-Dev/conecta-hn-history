<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class CampaignCreatePageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_campaign_model_accepts_tenant_assignment_and_pro_owner_can_open_create_page(): void
    {
        $this->assertContains('tenant_id', (new Campaign())->getFillable());

        [$user, $tenant] = $this->makeTenantOwner(isPro: true);

        $response = $this
            ->actingAs($user)
            ->get(route('filament.app.resources.campaigns.create', ['tenant' => $tenant->slug], false));

        $response->assertOk();
    }

    public function test_free_owner_cannot_open_campaign_create_page(): void
    {
        [$user, $tenant] = $this->makeTenantOwner(isPro: false);

        $response = $this
            ->actingAs($user)
            ->get(route('filament.app.resources.campaigns.create', ['tenant' => $tenant->slug], false));

        $response->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(bool $isPro): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $tenantFactory = Tenant::factory()
            ->state([
                'user_id' => $user->id,
                'slug' => $isPro ? 'tenant-pro-campaigns' : 'tenant-free-campaigns',
                'business_type' => 'retail',
            ]);

        if ($isPro) {
            $tenantFactory = $tenantFactory->pro();
        }

        $tenant = $tenantFactory->create();

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }
}
