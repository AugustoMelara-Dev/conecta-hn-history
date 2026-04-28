<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use Tests\TestCase;

final class PanelSameOriginRedirectTest extends TestCase
{
    public function test_app_panel_guest_redirect_stays_relative_under_proxy_hosts(): void
    {
        config([
            'app.url' => 'https://vito-business-os-production.up.railway.app',
            'app.platform_app_url' => 'https://vito-business-os-production.up.railway.app',
            'app.platform_admin_url' => 'https://vito-business-os-production.up.railway.app',
            'app.platform_directory_url' => 'https://vitobusinessos.vercel.app',
            'surfaces.origins.business_app' => 'https://vito-business-os-production.up.railway.app',
            'surfaces.origins.admin_app' => 'https://vito-business-os-production.up.railway.app',
            'surfaces.origins.user_app' => 'https://vitobusinessos.vercel.app',
        ]);

        $response = $this->withHeaders([
            'Host' => 'vitobusinessos.vercel.app',
            'X-Forwarded-Host' => 'vitobusinessos.vercel.app',
            'X-Forwarded-Proto' => 'https',
        ])->get('/app');

        $location = (string) $response->headers->get('Location', '');
        $path = (string) parse_url($location, PHP_URL_PATH);
        $response->assertStatus(302);
        $this->assertNotSame('', $location);
        $this->assertStringStartsWith('/app/login', $path);
        $this->assertStringNotContainsString('vito-business-os-production.up.railway.app', strtolower($location));
    }

    public function test_admin_panel_guest_redirect_stays_relative_under_proxy_hosts(): void
    {
        config([
            'app.url' => 'https://vito-business-os-production.up.railway.app',
            'app.platform_app_url' => 'https://vito-business-os-production.up.railway.app',
            'app.platform_admin_url' => 'https://vito-business-os-production.up.railway.app',
            'app.platform_directory_url' => 'https://vitobusinessos.vercel.app',
            'surfaces.origins.business_app' => 'https://vito-business-os-production.up.railway.app',
            'surfaces.origins.admin_app' => 'https://vito-business-os-production.up.railway.app',
            'surfaces.origins.user_app' => 'https://vitobusinessos.vercel.app',
        ]);

        $response = $this->withHeaders([
            'Host' => 'vitobusinessos.vercel.app',
            'X-Forwarded-Host' => 'vitobusinessos.vercel.app',
            'X-Forwarded-Proto' => 'https',
        ])->get('/admin');

        $location = (string) $response->headers->get('Location', '');
        $path = (string) parse_url($location, PHP_URL_PATH);
        $response->assertStatus(302);
        $this->assertNotSame('', $location);
        $this->assertStringStartsWith('/admin/login', $path);
        $this->assertStringNotContainsString('vito-business-os-production.up.railway.app', strtolower($location));
    }

    public function test_app_panel_guest_redirect_stays_relative_when_public_url_is_canonical(): void
    {
        config([
            'app.url' => 'https://vitobusinessos.vercel.app',
            'app.platform_app_url' => 'https://vitobusinessos.vercel.app',
            'app.platform_admin_url' => 'https://vitobusinessos.vercel.app',
            'app.platform_directory_url' => 'https://vitobusinessos.vercel.app',
            'surfaces.origins.business_app' => 'https://vitobusinessos.vercel.app',
            'surfaces.origins.admin_app' => 'https://vitobusinessos.vercel.app',
            'surfaces.origins.user_app' => 'https://vitobusinessos.vercel.app',
        ]);

        $response = $this->withHeaders([
            'Host' => 'vitobusinessos.vercel.app',
            'X-Forwarded-Host' => 'vitobusinessos.vercel.app',
            'X-Forwarded-Proto' => 'https',
        ])->get('/app');

        $location = (string) $response->headers->get('Location', '');
        $path = (string) parse_url($location, PHP_URL_PATH);
        $response->assertStatus(302);
        $this->assertNotSame('', $location);
        $this->assertStringStartsWith('/app/login', $path);
        $this->assertStringNotContainsString('vito-business-os-production.up.railway.app', strtolower($location));
    }
}
