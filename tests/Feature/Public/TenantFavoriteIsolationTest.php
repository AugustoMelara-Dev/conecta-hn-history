<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Application\Directory\Contracts\PublicTenantEngagementGatewayInterface;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantFavoriteIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_toggle_an_active_tenant_favorite_with_uuid_ids(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user)
            ->from('/directorio')
            ->post('/favorites/toggle', ['tenant_id' => $tenant->getKey()])
            ->assertRedirect('/directorio')
            ->assertSessionHas('success', 'Guardado en favoritos');

        $this->assertDatabaseHas('tenant_favorites', [
            'user_id' => $user->getKey(),
            'tenant_id' => $tenant->getKey(),
        ]);

        $this->actingAs($user)
            ->from('/directorio')
            ->post('/favorites/toggle', ['tenant_id' => $tenant->getKey()])
            ->assertRedirect('/directorio')
            ->assertSessionHas('success', 'Eliminado de favoritos');

        $this->assertDatabaseMissing('tenant_favorites', [
            'user_id' => $user->getKey(),
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function test_client_cannot_choose_the_user_who_owns_the_favorite(): void
    {
        $authenticatedUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($authenticatedUser)->post('/favorites/toggle', [
            'tenant_id' => $tenant->getKey(),
            'user_id' => $otherUser->getKey(),
        ])->assertRedirect();

        $this->assertDatabaseHas('tenant_favorites', [
            'user_id' => $authenticatedUser->getKey(),
            'tenant_id' => $tenant->getKey(),
        ]);
        $this->assertDatabaseMissing('tenant_favorites', [
            'user_id' => $otherUser->getKey(),
            'tenant_id' => $tenant->getKey(),
        ]);
    }

    public function test_inactive_or_non_public_tenant_cannot_be_favorited(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->inactive()->create();

        $this->actingAs($user)
            ->from('/directorio')
            ->post('/favorites/toggle', ['tenant_id' => $tenant->getKey()])
            ->assertRedirect('/directorio')
            ->assertSessionHasErrors('tenant_id');

        $this->assertDatabaseCount('tenant_favorites', 0);
    }

    public function test_follow_gateway_uses_uuid_and_explicit_public_tenant_filter(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $gateway = $this->app->make(PublicTenantEngagementGatewayInterface::class);

        $followed = $gateway->toggleFollow((string) $user->getKey(), (string) $tenant->getKey());

        self::assertNotNull($followed);
        self::assertTrue($followed->engaged);
        self::assertSame(1, $followed->count);
        $this->assertDatabaseHas('follows', [
            'user_id' => $user->getKey(),
            'tenant_id' => $tenant->getKey(),
        ]);

        $unfollowed = $gateway->toggleFollow((string) $user->getKey(), (string) $tenant->getKey());

        self::assertNotNull($unfollowed);
        self::assertFalse($unfollowed->engaged);
        self::assertSame(0, $unfollowed->count);
    }

    public function test_authenticated_api_user_can_follow_and_unfollow_a_public_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tenants/{$tenant->getKey()}/follow")
            ->assertOk()
            ->assertJsonPath('following', true)
            ->assertJsonPath('followers_count', 1);

        $this->assertDatabaseHas('follows', [
            'user_id' => $user->getKey(),
            'tenant_id' => $tenant->getKey(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tenants/{$tenant->getKey()}/follow")
            ->assertOk()
            ->assertJsonPath('following', false)
            ->assertJsonPath('followers_count', 0);
    }

    public function test_follow_api_rejects_guests_and_inactive_tenants(): void
    {
        $tenant = Tenant::factory()->inactive()->create();

        $this->postJson("/api/v1/tenants/{$tenant->getKey()}/follow")
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/v1/tenants/{$tenant->getKey()}/follow")
            ->assertNotFound();

        $this->assertDatabaseCount('follows', 0);
    }
}
