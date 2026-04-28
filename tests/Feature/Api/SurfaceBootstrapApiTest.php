<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class SurfaceBootstrapApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_receives_split_bootstrap_with_legacy_adapters(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'slug' => 'cafe-vito',
        ]);
        $owner->forceFill(['tenant_id' => $tenant->getKey()])->save();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/surfaces/bootstrap');

        $response->assertOk()
            ->assertJsonPath('data.context.home_surface', 'business_app')
            ->assertJsonPath('data.context.audience', 'owner')
            ->assertJsonPath('meta.version', 'surface-bootstrap-v1');

        $legacy = collect((array) $response->json('data.compatibility.legacy_paths'));
        $workspace = $legacy->firstWhere('path', '/workspace/dashboard');

        self::assertIsArray($workspace);
        self::assertSame(route('filament.app.pages.dashboard', ['tenant' => 'cafe-vito'], false), data_get($workspace, 'redirect_to'));
    }

    public function test_surface_bootstrap_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/surfaces/bootstrap');

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}
