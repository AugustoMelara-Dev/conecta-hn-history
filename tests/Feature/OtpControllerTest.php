<?php

namespace Tests\Feature;

use App\Models\OneTimePassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake(); // Prevent actual emails
        RateLimiter::clear('otp-generate:127.0.0.1:test@example.com');
        RateLimiter::clear('otp-verify:127.0.0.1:test@example.com');
    }

    /** @test */
    public function it_generates_otp_for_valid_email()
    {
        $response = $this->postJson('/api/otp/generate', [
            'identifier' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'expires_at']);

        $this->assertDatabaseHas('one_time_passwords', [
            'identifier' => 'test@example.com',
        ]);
    }

    /** @test */
    public function it_generates_otp_for_valid_phone()
    {
        $response = $this->postJson('/api/otp/generate', [
            'identifier' => '+50412345678',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('one_time_passwords', [
            'identifier' => '+50412345678',
        ]);
    }

    /** @test */
    public function it_rejects_invalid_identifier_format()
    {
        $response = $this->postJson('/api/otp/generate', [
            'identifier' => 'not-valid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('identifier');
    }

    /** @test */
    public function it_enforces_rate_limit_on_generation()
    {
        // Make 3 successful requests
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/otp/generate', [
                'identifier' => 'limit@test.com',
            ])->assertStatus(200);
        }

        // 4th request should be rate limited
        $response = $this->postJson('/api/otp/generate', [
            'identifier' => 'limit@test.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('identifier');
    }

    /** @test */
    public function it_verifies_correct_otp_code()
    {
        // Generate OTP
        $code = '123456';
        OneTimePassword::create([
            'identifier' => 'verify@test.com',
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->postJson('/api/otp/verify', [
            'identifier' => 'verify@test.com',
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('requires_registration', true);
    }

    /** @test */
    public function it_rejects_incorrect_otp_code()
    {
        OneTimePassword::create([
            'identifier' => 'verify@test.com',
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->postJson('/api/otp/verify', [
            'identifier' => 'verify@test.com',
            'code' => '000000', // Wrong code
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    /** @test */
    public function it_rejects_expired_otp()
    {
        OneTimePassword::create([
            'identifier' => 'expired@test.com',
            'code' => '123456',
            'expires_at' => now()->subMinutes(1), // Expired
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->postJson('/api/otp/verify', [
            'identifier' => 'expired@test.com',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    /** @test */
    public function it_logs_in_existing_user_after_otp_verification()
    {
        $user = User::factory()->create([
            'email' => 'existing@test.com',
            'role' => 'buyer',
        ]);

        $code = '123456';
        OneTimePassword::create([
            'user_id' => $user->id,
            'identifier' => 'existing@test.com',
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->postJson('/api/otp/verify', [
            'identifier' => 'existing@test.com',
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Autenticación exitosa.')
            ->assertJsonPath('redirect', '/mi-cuenta');

        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function it_enforces_rate_limit_on_verification()
    {
        OneTimePassword::create([
            'identifier' => 'ratelimit@test.com',
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
        ]);

        // 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/otp/verify', [
                'identifier' => 'ratelimit@test.com',
                'code' => '000000',
            ]);
        }

        // 6th attempt should be locked
        $response = $this->postJson('/api/otp/verify', [
            'identifier' => 'ratelimit@test.com',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.code.0', function ($message) {
                return str_contains($message, 'bloqueado por seguridad');
            });
    }

    /** @test */
    public function it_deletes_otp_after_successful_verification()
    {
        $code = '123456';
        OneTimePassword::create([
            'identifier' => 'delete@test.com',
            'code' => $code,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => '127.0.0.1',
        ]);

        $this->postJson('/api/otp/verify', [
            'identifier' => 'delete@test.com',
            'code' => $code,
        ]);

        $this->assertDatabaseMissing('one_time_passwords', [
            'identifier' => 'delete@test.com',
        ]);
    }
}
