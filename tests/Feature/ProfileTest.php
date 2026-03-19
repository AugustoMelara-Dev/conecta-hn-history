<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->withTenant()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->withTenant()->create();
        $token = 'profile-update-token';

        $response = $this
            ->withSession(['_token' => $token])
            ->actingAs($user)
            ->patch('/profile', [
                '_token' => $token,
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->withTenant()->create();
        $token = 'profile-email-token';

        $response = $this
            ->withSession(['_token' => $token])
            ->actingAs($user)
            ->patch('/profile', [
                '_token' => $token,
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->withTenant()->create();
        $token = 'profile-delete-token';

        $response = $this
            ->withSession(['_token' => $token])
            ->actingAs($user)
            ->delete('/profile', [
                '_token' => $token,
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->withTenant()->create();
        $token = 'profile-delete-invalid-token';

        $response = $this
            ->withSession(['_token' => $token])
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                '_token' => $token,
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
