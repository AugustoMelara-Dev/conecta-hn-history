<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnalyticsDataTenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_owner_receives_tenant_scoped_analytics_without_a_server_error(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();

        $this->actingAs($owner)
            ->getJson('/analytics/data?days=7')
            ->assertOk()
            ->assertJsonStructure(['data', 'summary']);
    }
}
