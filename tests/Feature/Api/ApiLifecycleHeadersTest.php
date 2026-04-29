<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

final class ApiLifecycleHeadersTest extends TestCase
{
    public function test_v1_responses_advertise_their_api_lifecycle_contract(): void
    {
        config([
            'api-lifecycle.supported_versions' => ['v1'],
            'api-lifecycle.versions.v1.status' => 'active',
            'api-lifecycle.versions.v1.policy_url' => 'https://vitobusinessos.com/docs/api/versioning',
        ]);

        $response = $this->postJson('/api/v1/otp/verify', []);

        $response->assertUnprocessable();
        $response->assertHeader('X-API-Version', 'v1');
        $response->assertHeader('X-API-Supported-Versions', 'v1');
        $response->assertHeader('Link', '<https://vitobusinessos.com/docs/api/versioning>; rel="version-policy"');
        $response->assertHeaderMissing('Deprecation');
        $response->assertHeaderMissing('Sunset');
    }

    public function test_v1_auth_failures_still_advertise_their_api_lifecycle_contract(): void
    {
        config([
            'api-lifecycle.supported_versions' => ['v1'],
            'api-lifecycle.versions.v1.status' => 'active',
            'api-lifecycle.versions.v1.policy_url' => 'https://vitobusinessos.com/docs/api/versioning',
        ]);

        $response = $this->getJson('/api/v1/user');

        $response->assertUnauthorized();
        $response->assertHeader('X-API-Version', 'v1');
        $response->assertHeader('X-API-Supported-Versions', 'v1');
        $response->assertHeader('Link', '<https://vitobusinessos.com/docs/api/versioning>; rel="version-policy"');
    }

    public function test_deprecated_versions_emit_sunset_and_successor_headers(): void
    {
        config([
            'api-lifecycle.supported_versions' => ['v1', 'v2'],
            'api-lifecycle.versions.v1.status' => 'deprecated',
            'api-lifecycle.versions.v1.sunset_at' => 'Wed, 31 Dec 2026 23:59:59 GMT',
            'api-lifecycle.versions.v1.successor' => 'v2',
            'api-lifecycle.versions.v1.policy_url' => 'https://vitobusinessos.com/docs/api/versioning',
        ]);

        $response = $this->postJson('/api/v1/otp/verify', []);

        $response->assertUnprocessable();
        $response->assertHeader('X-API-Version', 'v1');
        $response->assertHeader('X-API-Supported-Versions', 'v1, v2');
        $response->assertHeader('Deprecation', 'true');
        $response->assertHeader('Sunset', 'Wed, 31 Dec 2026 23:59:59 GMT');

        $links = $response->headers->all('link');

        self::assertContains('<https://vitobusinessos.com/docs/api/versioning>; rel="version-policy"', $links);
        self::assertContains('</api/v2>; rel="successor-version"', $links);
    }
}
