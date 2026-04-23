<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * SecurityHeaders Middleware — CSP Invariant Tests
 *
 * INVARIANT: 'unsafe-eval' MUST appear in script-src if and only if
 *            the application is NOT running in the production environment.
 */

// ═══════════════════════════════════════════════════════════════════════════
// Helper: Extract script-src directive from full CSP header
// ═══════════════════════════════════════════════════════════════════════════
function extractScriptSrc(string $csp): string
{
    if (preg_match('/script-src\s+([^;]+)/', $csp, $m)) {
        return trim($m[1]);
    }

    return '';
}

function extractConnectSrc(string $csp): string
{
    if (preg_match('/connect-src\s+([^;]+)/', $csp, $m)) {
        return trim($m[1]);
    }

    return '';
}

// ═══════════════════════════════════════════════════════════════════════════
// CSP: unsafe-eval Isolation Invariant
// ═══════════════════════════════════════════════════════════════════════════

it('allows unsafe-eval for scripts in local environment only', function () {
    // Test environment is non-production, so unsafe-eval should be present
    $middleware = new SecurityHeaders;
    $request = Request::create('/register', 'GET');

    $response = $middleware->handle($request, fn () => new Response('OK'));

    $csp = $response->headers->get('Content-Security-Policy');
    $scriptSrc = extractScriptSrc($csp);

    // Dev/testing script-src must include unsafe-eval for Vite HMR
    expect($scriptSrc)->toContain("'unsafe-eval'");
    // Dev/testing script-src must include unsafe-inline for Vite HMR
    expect($scriptSrc)->toContain("'unsafe-inline'");
});

it('forbids unsafe-eval in production environment', function () {
    // Override the environment at container level
    app()['env'] = 'production';

    try {
        $middleware = new SecurityHeaders;
        $request = Request::create('/register', 'GET');

        $response = $middleware->handle($request, fn () => new Response('OK'));

        $csp = $response->headers->get('Content-Security-Policy');
        $scriptSrc = extractScriptSrc($csp);
    } finally {
        app()['env'] = 'testing';
    }

    // Production script-src must NEVER include unsafe-eval
    expect($scriptSrc)->not->toContain("'unsafe-eval'");
    // Production script-src must use nonce, not unsafe-inline
    expect($scriptSrc)->not->toContain("'unsafe-inline'");
});

it('includes nonce in production script-src', function () {
    // Override the environment at container level
    app()['env'] = 'production';

    try {
        $middleware = new SecurityHeaders;
        $request = Request::create('/', 'GET');

        $response = $middleware->handle($request, fn () => new Response('OK'));

        $csp = $response->headers->get('Content-Security-Policy');
        $scriptSrc = extractScriptSrc($csp);
    } finally {
        app()['env'] = 'testing';
    }

    expect($scriptSrc)->toMatch("/'nonce-[A-Za-z0-9+\/=]+'/");
});

it('includes runtime surface origins in production connect-src', function () {
    config()->set('app.url', 'https://vito-business-os-production.up.railway.app');
    config()->set('app.platform_app_url', 'https://vitobusinessos.vercel.app');
    config()->set('app.platform_directory_url', 'https://vitobusinessos.vercel.app');
    config()->set('surfaces.origins.platform_api', 'https://api.vitobusinessos.com');

    app()['env'] = 'production';

    try {
        $middleware = new SecurityHeaders;
        $request = Request::create('/', 'GET');

        $response = $middleware->handle($request, fn () => new Response('OK'));

        $csp = $response->headers->get('Content-Security-Policy');
        $connectSrc = extractConnectSrc($csp);
    } finally {
        app()['env'] = 'testing';
    }

    expect($connectSrc)->toContain('https://vito-business-os-production.up.railway.app');
    expect($connectSrc)->toContain('https://vitobusinessos.vercel.app');
    expect($connectSrc)->toContain('https://api.vitobusinessos.com');
});
