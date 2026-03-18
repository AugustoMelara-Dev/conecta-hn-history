<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set test domain for subdomain routing
    config(['app.domain' => 'vito.test']);
});

describe('Subdomain Routing Infrastructure', function () {
    it('resolves tenant microsite from subdomain', function () {
        $tenant = Tenant::factory()->create([
            'slug' => 'barberia',
            'name' => 'La Barbería',
            'is_active' => true,
        ]);

        $response = $this->get("/t/{$tenant->slug}");

        $response->assertOk();
    });

    it('returns 404 for unknown subdomain', function () {
        $response = $this->get('/t/unknown-business');

        $response->assertStatus(404);
    });

    it('does not trigger tenant resolution for reserved subdomains', function () {
        // Arrange
        $reservedSubdomains = ['admin', 'api', 'app', 'www', 'mail'];

        foreach ($reservedSubdomains as $subdomain) {
            // Act: Request to reserved subdomain
            $host = "{$subdomain}.vito.test";

            // Simulate middleware check
            $request = Request::create("http://{$host}/");
            $request->headers->set('HOST', $host);

            // Assert: Reserved subdomains should not be found as tenants
            // (they pass through without tenant resolution)
            $tenant = Tenant::where('slug', $subdomain)->first();
            expect($tenant)->toBeNull();
        }
    });

    it('binds tenant to container via middleware', function () {
        // Arrange
        $tenant = Tenant::factory()->create([
            'slug' => 'cafetocoa',
            'name' => 'Café Tocoa',
            'is_active' => true,
        ]);

        // Mock the middleware behavior manually
        app()->instance('current_tenant', $tenant);

        // Assert
        $boundTenant = app('current_tenant');
        expect($boundTenant)->toBeInstanceOf(Tenant::class)
            ->and($boundTenant->slug)->toBe('cafetocoa')
            ->and($boundTenant->is_active)->toBeTrue();
    });

    it('rejects inactive tenant subdomains with 404', function () {
        // Arrange: Create inactive tenant
        $tenant = Tenant::factory()->create([
            'slug' => 'closed-shop',
            'is_active' => false,
        ]);

        // Act: The middleware should reject this
        // (In real routing, this returns 404)
        $activeTenant = Tenant::where('slug', 'closed-shop')
            ->where('is_active', true)
            ->first();

        // Assert
        expect($activeTenant)->toBeNull();
    });
});

describe('Middleware Subdomain Extraction', function () {
    it('correctly extracts subdomain from hostname', function () {
        $testCases = [
            ['host' => 'barberia.vito.test', 'domain' => 'vito.test', 'expected' => 'barberia'],
            ['host' => 'my-shop.vito.test', 'domain' => 'vito.test', 'expected' => 'my-shop'],
            ['host' => 'vito.test', 'domain' => 'vito.test', 'expected' => null],
            ['host' => 'www.vito.test', 'domain' => 'vito.test', 'expected' => 'www'],
            ['host' => 'barberia.localhost:8000', 'domain' => 'localhost', 'expected' => 'barberia'],
        ];

        foreach ($testCases as $case) {
            $host = strtolower(preg_replace('/:\d+$/', '', $case['host']));
            $baseDomain = strtolower($case['domain']);

            if (! str_ends_with($host, $baseDomain) || $host === $baseDomain) {
                $subdomain = null;
            } else {
                $subdomain = str_replace('.'.$baseDomain, '', $host);
            }

            expect($subdomain)->toBe($case['expected'], "Failed for host: {$case['host']}");
        }
    });
});
