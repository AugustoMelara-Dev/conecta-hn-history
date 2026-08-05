<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AccountPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_download_a_secret_free_portable_export(): void
    {
        $user = User::factory()->create([
            'email' => 'portable@example.com',
            'remember_token' => 'must-not-leak',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get('/mi-cuenta/exportar');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('format', 'conecta-hn-account-export-v1')
            ->assertJsonPath('profile.email', 'portable@example.com');

        $body = $response->getContent();
        self::assertStringNotContainsString('must-not-leak', $body);
        self::assertStringNotContainsString('password', strtolower($body));
        self::assertStringNotContainsString('two_factor_secret', $body);
    }

    public function test_customer_deletion_revokes_tokens_and_persisted_sessions(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser(User::factory()->create())->create();
        $user->createToken('privacy-test');

        $confirmationId = (string) Str::uuid();
        DB::table('ai_tool_confirmations')->insert([
            'id' => $confirmationId,
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'tool' => 'tenant_lead.prepare',
            'payload' => 'encrypted-payload',
            'payload_hash' => hash('sha256', 'payload'),
            'confirmation_token' => 'encrypted-token',
            'confirmation_token_hash' => hash('sha256', 'token'),
            'idempotency_key' => 'privacy-delete',
            'status' => 'cancelled',
            'expires_at' => now()->subMinute(),
            'cancelled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sessions')->insert([
            'id' => 'other-active-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'privacy-test',
            'payload' => 'opaque',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->delete('/mi-cuenta', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('ai_tool_confirmations', ['id' => $confirmationId]);
    }

    public function test_business_owner_must_transfer_or_close_business_before_account_deletion(): void
    {
        $user = User::factory()->create();
        Tenant::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from('/mi-cuenta')
            ->delete('/mi-cuenta', ['password' => 'password'])
            ->assertRedirect('/mi-cuenta')
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertAuthenticatedAs($user);
    }
}
