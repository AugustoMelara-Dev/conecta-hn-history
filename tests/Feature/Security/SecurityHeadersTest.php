<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_api_middleware_group_applies_browser_security_headers(): void
    {
        Route::middleware('api')->get('/__security_test/headers', fn () => response()->json(['ok' => true]));

        $response = $this->getJson('/__security_test/headers');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-XSS-Protection', '0')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        self::assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_production_csp_uses_the_same_nonce_for_vite_and_ziggy(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'security.hsts.enabled' => true,
            'security.hsts.include_subdomains' => false,
            'security.hsts.preload' => false,
        ]);

        $request = Request::create('https://conecta.test/', 'GET');
        $response = (new SecurityHeaders)->handle(
            $request,
            fn (): Response => response(Blade::render('@routes(null, Vite::cspNonce())'))
        );

        $nonce = Vite::cspNonce();
        self::assertIsString($nonce);
        self::assertNotSame('', $nonce);
        self::assertStringContainsString("'nonce-{$nonce}'", (string) $response->headers->get('Content-Security-Policy'));
        self::assertStringContainsString('nonce="'.$nonce.'"', (string) $response->getContent());
        self::assertSame('max-age=31536000', $response->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_is_absent_when_disabled_and_does_not_imply_subdomains_or_preload(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'security.hsts.enabled' => false,
            'security.hsts.include_subdomains' => true,
            'security.hsts.preload' => true,
        ]);

        $response = (new SecurityHeaders)->handle(
            Request::create('https://conecta.test/', 'GET'),
            fn (): Response => response('ok')
        );

        self::assertFalse($response->headers->has('Strict-Transport-Security'));
    }
}
