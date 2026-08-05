<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ApiLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_only_the_presented_bearer_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current-device', ['tenant:test']);
        $other = $user->createToken('other-device', ['tenant:test']);

        $this->withToken($current->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_persisted_bearer_token_cannot_cross_tenant_ability_scope(): void
    {
        Route::middleware(['auth:sanctum', 'tenant.context'])
            ->get('/_test/tenant-token-scope', static fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create();
        $user->forceFill(['tenant_id' => $tenant->id])->save();
        $token = $user->createToken('wrong-tenant', ['tenant:00000000-0000-0000-0000-000000000000']);

        $this->withToken($token->plainTextToken)
            ->withHeader('X-Tenant-ID', (string) $tenant->id)
            ->getJson('/_test/tenant-token-scope')
            ->assertForbidden();
    }
}
