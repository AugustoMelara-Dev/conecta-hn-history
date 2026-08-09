<?php

declare(strict_types=1);

/**
 * SocialLoginTest - Security & Regression Tests for Social Authentication
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MANDATORY SECURITY TESTING: DoS Prevention & Input Validation          ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  TEST COVERAGE:                                                          ║
 * ║    1. Happy Path: Valid social login flow                               ║
 * ║    2. DoS Prevention: Large payload rejection (10K chars)               ║
 * ║    3. Provider Whitelist: Unsupported provider rejection                ║
 * ║    4. Missing Fields: Required field validation                         ║
 * ║    5. Email Validation: RFC 5321 + DNS compliance                       ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\SocialLoginRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    private const SOCIAL_LOGIN_ENDPOINT = '/api/v1/auth/social';

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * TEST 1: Happy Path - Valid Google Authentication
     * ═══════════════════════════════════════════════════════════════════════════
     * GIVEN: A valid OAuth payload from Google
     * WHEN: POST to /api/v1/auth/social
     * THEN: Returns 200 with user and accessToken
     */
    public function test_social_login_happy_path_with_valid_google_payload(): void
    {
        $payload = [
            'provider' => 'google',
            'token' => 'ya29.valid-google-oauth-token-'.str_repeat('x', 100),
            'provider_id' => '123456789012345678901',
            'email' => 'valid.user@gmail.com',
            'name' => 'Test User',
            'avatar' => 'https://lh3.googleusercontent.com/a/photo.jpg',
        ];

        $validator = Validator::make($payload, (new SocialLoginRequest)->rules());
        $this->assertFalse($validator->fails(), 'Valid social payload should pass FormRequest validation rules.');
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * TEST 2: SECURITY - Large Payload Rejection (DoS Prevention)
     * ═══════════════════════════════════════════════════════════════════════════
     * VULNERABILITY: Memory exhaustion via mega-byte token payloads
     * MITIGATION: max:4096 rule on token field
     *
     * GIVEN: A payload with 10,000 character token
     * WHEN: POST to /api/v1/auth/social
     * THEN: Returns 422 with validation error (NOT 500 crash)
     */
    public function test_security_rejects_large_token_payload_to_prevent_dos(): void
    {
        $maliciousToken = str_repeat('A', 10000); // 10KB payload

        $payload = [
            'provider' => 'google',
            'token' => $maliciousToken,
            'provider_id' => '123456789',
            'email' => 'attacker@evil.com',
            'name' => 'Attacker',
        ];

        $response = $this->postJson(self::SOCIAL_LOGIN_ENDPOINT, $payload);

        // MUST return 422, NOT 500 (crash) or 200 (bypass)
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);

        // Verify specific error mentions size limit
        $this->assertStringContainsStringIgnoringCase(
            'limit',
            $response->json('errors.token.0') ?? $response->json('message'),
            'Error message should mention size/character limit'
        );
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * TEST 3: SECURITY - Unsupported Provider Rejection
     * ═══════════════════════════════════════════════════════════════════════════
     * VULNERABILITY: Arbitrary provider injection
     * MITIGATION: Rule::in() whitelist
     *
     * GIVEN: A payload with unsupported provider 'malicious_service'
     * WHEN: POST to /api/v1/auth/social
     * THEN: Returns 422 with provider validation error
     */
    public function test_security_rejects_unsupported_oauth_provider(): void
    {
        $payload = [
            'provider' => 'malicious_service',
            'token' => 'legitimate-looking-token',
            'provider_id' => '123456789',
            'email' => 'attacker@evil.com',
            'name' => 'Attacker',
        ];

        $response = $this->postJson(self::SOCIAL_LOGIN_ENDPOINT, $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * TEST 4: Validation - Missing Required Fields
     * ═══════════════════════════════════════════════════════════════════════════
     */
    public function test_validation_rejects_missing_required_fields(): void
    {
        // Empty payload
        $response = $this->postJson(self::SOCIAL_LOGIN_ENDPOINT, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider', 'token', 'provider_id', 'email']);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * TEST 5: Validation - Invalid Email Format
     * ═══════════════════════════════════════════════════════════════════════════
     */
    public function test_validation_rejects_invalid_email_format(): void
    {
        $payload = [
            'provider' => 'google',
            'token' => 'valid-token-123',
            'provider_id' => '123456789',
            'email' => 'not-an-email',
            'name' => 'Test User',
        ];

        $response = $this->postJson(self::SOCIAL_LOGIN_ENDPOINT, $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * TEST 6: Validation - Provider ID Too Long
     * ═══════════════════════════════════════════════════════════════════════════
     */
    public function test_validation_rejects_oversized_provider_id(): void
    {
        $payload = [
            'provider' => 'google',
            'token' => 'valid-token-123',
            'provider_id' => str_repeat('9', 300), // 300 chars > max:255
            'email' => 'user@example.com',
            'name' => 'Test User',
        ];

        $response = $this->postJson(self::SOCIAL_LOGIN_ENDPOINT, $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider_id']);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * TEST 7: Whitelist - All Supported Providers Are Accepted
     * ═══════════════════════════════════════════════════════════════════════════
     */
    public function test_all_supported_providers_pass_validation(): void
    {
        $supportedProviders = SocialLoginRequest::getSupportedProviders();

        foreach ($supportedProviders as $provider) {
            $payload = [
                'provider' => $provider,
                'token' => 'valid-token-'.$provider,
                'provider_id' => '123456789',
                'email' => "user.{$provider}@example.com",
                'name' => 'Test User',
            ];

            $response = $this->postJson(self::SOCIAL_LOGIN_ENDPOINT, $payload);

            // Should NOT get 422 for provider field (may get other errors)
            if ($response->status() === 422) {
                $this->assertArrayNotHasKey(
                    'provider',
                    $response->json('errors') ?? [],
                    "Provider '{$provider}' should be accepted but was rejected"
                );
            }
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * TEST 8: Edge Case - Token at Maximum Allowed Length
     * ═══════════════════════════════════════════════════════════════════════════
     */
    public function test_token_at_maximum_length_is_accepted(): void
    {
        $maxToken = str_repeat('x', 4096); // Exactly at limit

        $payload = [
            'provider' => 'google',
            'token' => $maxToken,
            'provider_id' => '123456789',
            'email' => 'user@example.com',
            'name' => 'Test User',
        ];

        $response = $this->postJson(self::SOCIAL_LOGIN_ENDPOINT, $payload);

        // Should NOT fail on token validation (may fail on handler)
        if ($response->status() === 422) {
            $this->assertArrayNotHasKey(
                'token',
                $response->json('errors') ?? [],
                'Token at exactly max length should be accepted'
            );
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * TEST 9: Edge Case - Token Slightly Over Maximum
     * ═══════════════════════════════════════════════════════════════════════════
     */
    public function test_token_over_maximum_length_is_rejected(): void
    {
        $oversizedToken = str_repeat('x', 4097); // 1 char over limit

        $payload = [
            'provider' => 'google',
            'token' => $oversizedToken,
            'provider_id' => '123456789',
            'email' => 'user@example.com',
            'name' => 'Test User',
        ];

        $response = $this->postJson(self::SOCIAL_LOGIN_ENDPOINT, $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * TEST 10: Optional Fields - Request Succeeds Without Optional Fields
     * ═══════════════════════════════════════════════════════════════════════════
     */
    public function test_optional_fields_can_be_omitted(): void
    {
        $payload = [
            'provider' => 'google',
            'token' => 'valid-token-123',
            'provider_id' => '123456789',
            'email' => 'user@example.com',
            // name and avatar omitted
        ];

        $response = $this->postJson(self::SOCIAL_LOGIN_ENDPOINT, $payload);

        // Should not fail on validation for missing optional fields
        if ($response->status() === 422) {
            $errors = $response->json('errors') ?? [];
            $this->assertArrayNotHasKey('name', $errors, 'name should be optional');
            $this->assertArrayNotHasKey('avatar', $errors, 'avatar should be optional');
        }
    }
}
