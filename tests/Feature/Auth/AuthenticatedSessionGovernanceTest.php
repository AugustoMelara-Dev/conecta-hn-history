<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Infrastructure\Auth\AuthenticatedSessionRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuthenticatedSessionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
        app('session')->forgetDrivers();
    }

    public function test_password_login_creates_hash_only_inventory_and_logout_revokes_it(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();
        $this->get(route('profile.edit'))->assertOk();

        $sessionId = session()->getId();
        $inventory = DB::table('authenticated_sessions')
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->orderByDesc('updated_at')
            ->first();

        self::assertNotNull($inventory);
        self::assertSame(64, strlen((string) $inventory->session_id_hash));
        self::assertStringNotContainsString($sessionId, json_encode($inventory, JSON_THROW_ON_ERROR));
        self::assertNull($inventory->revoked_at);

        $this->post('/logout')->assertRedirect('/');

        self::assertGreaterThanOrEqual(1, DB::table('authenticated_sessions')
            ->where('user_id', $user->id)
            ->whereNotNull('revoked_at')
            ->count());
        $this->assertGuest();
    }

    public function test_user_can_list_and_revoke_a_remote_session_after_password_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('profile.sessions.index'))->assertOk();
        $currentInventory = DB::table('authenticated_sessions')
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->latest('updated_at')
            ->firstOrFail();
        $remoteId = 'remote-session-id';
        $remoteInventoryId = $this->inventory($user, $remoteId);
        DB::table('sessions')->insert([
            'id' => $remoteId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Remote Browser',
            'payload' => 'remote',
            'last_activity' => time(),
        ]);

        $this->get(route('profile.sessions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Sessions')
                ->has('sessions')
                ->missing('sessions.0.session_id_hash')
                ->missing('sessions.0.ip_address_hash'));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('profile.sessions.destroy', ['session' => $remoteInventoryId]))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => $remoteId]);
        self::assertNotNull(
            DB::table('authenticated_sessions')->where('id', $remoteInventoryId)->value('revoked_at'),
        );
        self::assertNull(
            DB::table('authenticated_sessions')
                ->where('id', $currentInventory->id)
                ->value('revoked_at'),
        );
    }

    public function test_revoke_others_keeps_current_session_and_removes_all_remote_framework_sessions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('profile.sessions.index'))->assertOk();

        $remoteInventoryIds = [];
        foreach (['remote-one', 'remote-two'] as $remoteId) {
            $remoteInventoryIds[] = $this->inventory($user, $remoteId);
            DB::table('sessions')->insert([
                'id' => $remoteId,
                'user_id' => $user->id,
                'ip_address' => null,
                'user_agent' => 'Remote',
                'payload' => 'remote',
                'last_activity' => time(),
            ]);
        }

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('profile.sessions.destroy-others'))
            ->assertRedirect();

        self::assertSame(0, DB::table('sessions')
            ->where('user_id', $user->id)
            ->whereIn('id', ['remote-one', 'remote-two'])
            ->count());
        self::assertSame(2, DB::table('authenticated_sessions')
            ->whereIn('id', $remoteInventoryIds)
            ->whereNotNull('revoked_at')
            ->count());
        self::assertSame(1, DB::table('authenticated_sessions')
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->count());
        $this->assertAuthenticatedAs($user);
    }

    private function inventory(User $user, string $sessionId): string
    {
        $id = (string) Str::uuid();
        DB::table('authenticated_sessions')->insert([
            'id' => $id,
            'session_id_hash' => app(AuthenticatedSessionRegistry::class)->sessionHash($sessionId),
            'user_id' => $user->id,
            'current_tenant_id' => null,
            'device_name' => 'Remote Browser',
            'ip_address_hash' => null,
            'user_agent' => 'Remote Browser',
            'last_authenticated_at' => now()->subHour(),
            'mfa_verified_at' => null,
            'last_seen_at' => now()->subMinute(),
            'revoked_at' => null,
            'revocation_reason' => null,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subMinute(),
        ]);

        return $id;
    }
}
