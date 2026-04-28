<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AuthRateLimitHardeningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_throttles_forgot_password_requests_to_prevent_abuse(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $response = $this->post('/forgot-password', [
                'email' => 'rate-limit@example.test',
            ]);

            $response->assertStatus(302);
        }

        $throttled = $this->post('/forgot-password', [
            'email' => 'rate-limit@example.test',
        ]);

        $throttled->assertStatus(429);
    }

    public function test_throttles_slug_check_endpoint_after_burst_traffic(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $response = $this->get('/api/check-slug?slug=burst-check');
            $response->assertStatus(200);
        }

        $throttled = $this->get('/api/check-slug?slug=burst-check');
        $throttled->assertStatus(429);
    }
}
