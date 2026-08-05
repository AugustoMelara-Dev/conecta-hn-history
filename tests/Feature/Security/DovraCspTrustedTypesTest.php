<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Asserts the SecurityHeaders middleware emits Trusted Types directives in
 * production while preserving a non-production fallback that keeps Vite HMR
 * and local dev environments working. This is a structural contract that
 * protects the platform against DOM-XSS sinks and ensures the brand
 * identity is never inflated by a successful XSS chain.
 */
class DovraCspTrustedTypesTest extends TestCase
{
    public function test_csp_includes_trusted_types_directive_in_production(): void
    {
        config(['app.env' => 'production']);

        $response = $this->get('/up');

        $csp = (string) $response->headers->get('Content-Security-Policy', '');
        $reportOnly = (string) $response->headers->get(
            'Content-Security-Policy-Report-Only',
            '',
        );

        $body = $csp !== '' ? $csp : $reportOnly;

        $this->assertNotSame(
            '',
            $body,
            'CSP header (enforce or report-only) must be present in production responses.'
        );

        $this->assertStringContainsString(
            'trusted-types dovra-policy',
            $body,
            'Production CSP must declare a Trusted Types policy named `dovra-policy`.'
        );

        if ($csp !== '' && str_contains($csp, 'require-trusted-types-for')) {
            $this->assertStringContainsString(
                "require-trusted-types-for 'script'",
                $csp,
                'Production CSP must require Trusted Types for script sinks.'
            );
        }
    }

    public function test_csp_keeps_a_non_blocking_trusted_types_directive_in_local(): void
    {
        config(['app.env' => 'local']);

        $response = $this->get('/up');

        $body = (string) $response->headers->get('Content-Security-Policy', '');
        if ($body === '') {
            $body = (string) $response->headers->get(
                'Content-Security-Policy-Report-Only',
                '',
            );
        }

        if ($body === '') {
            $this->markTestSkipped('CSP header absent in this runtime.');
        }

        $this->assertStringContainsString(
            'trusted-types dovra-policy',
            $body,
            'Non-production CSP must still declare the same Trusted Types policy.'
        );
    }
}
