<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $user->createToken('existing-device', ['tenant:'.$tenant->id]);
        DB::table('sessions')->insert([
            'id' => 'other-web-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'other',
            'last_activity' => time(),
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'New-Password-123',
                'password_confirmation' => 'New-Password-123',
            ]);

        $response
            ->assertSessionHasNoErrors();

        $response->assertRedirect('/profile');

        $this->assertTrue(Hash::check('New-Password-123', $user->refresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['id' => 'other-web-session']);
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'New-Password-123',
                'password_confirmation' => 'New-Password-123',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');
    }
}
