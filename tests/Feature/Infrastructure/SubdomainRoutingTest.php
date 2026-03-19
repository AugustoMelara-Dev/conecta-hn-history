<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SubdomainRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.domain' => 'vito.test']);
    }

    #[Test]
    public function resolves_tenant_microsite_from_subdomain(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'barberia',
            'name' => 'La Barberia',
            'is_active' => true,
        ]);

        $response = $this->get('/t/'.(string) $tenant->slug);

        $response->assertOk();
    }

    #[Test]
    public function returns_404_for_unknown_subdomain(): void
    {
        $response = $this->get('/t/unknown-business');

        $response->assertStatus(404);
    }

    #[Test]
    public function does_not_trigger_tenant_resolution_for_reserved_subdomains(): void
    {
        /** @var list<string> $reservedSubdomains */
        $reservedSubdomains = ['admin', 'api', 'app', 'www', 'mail'];

        foreach ($reservedSubdomains as $subdomain) {
            $host = "{$subdomain}.vito.test";
            $request = Request::create("http://{$host}/");
            $request->headers->set('HOST', $host);

            $tenant = Tenant::query()->where('slug', $subdomain)->first();

            self::assertNull($tenant);
        }
    }

    #[Test]
    public function binds_tenant_to_container_via_middleware(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'cafetocoa',
            'name' => 'Cafe Tocoa',
            'is_active' => true,
        ]);

        app()->instance('current_tenant', $tenant);

        /** @var Tenant $boundTenant */
        $boundTenant = app('current_tenant');

        self::assertInstanceOf(Tenant::class, $boundTenant);
        self::assertSame('cafetocoa', (string) $boundTenant->slug);
        self::assertTrue((bool) $boundTenant->is_active);
    }

    #[Test]
    public function rejects_inactive_tenant_subdomains_with_404(): void
    {
        Tenant::factory()->create([
            'slug' => 'closed-shop',
            'is_active' => false,
        ]);

        $activeTenant = Tenant::query()
            ->where('slug', 'closed-shop')
            ->where('is_active', true)
            ->first();

        self::assertNull($activeTenant);
    }

    #[Test]
    public function correctly_extracts_subdomain_from_hostname(): void
    {
        /**
         * @var list<array{host: string, domain: string, expected: string|null}> $testCases
         */
        $testCases = [
            ['host' => 'barberia.vito.test', 'domain' => 'vito.test', 'expected' => 'barberia'],
            ['host' => 'my-shop.vito.test', 'domain' => 'vito.test', 'expected' => 'my-shop'],
            ['host' => 'vito.test', 'domain' => 'vito.test', 'expected' => null],
            ['host' => 'www.vito.test', 'domain' => 'vito.test', 'expected' => 'www'],
            ['host' => 'barberia.localhost:8000', 'domain' => 'localhost', 'expected' => 'barberia'],
        ];

        foreach ($testCases as $case) {
            $host = strtolower((string) preg_replace('/:\d+$/', '', $case['host']));
            $baseDomain = strtolower($case['domain']);

            if (! str_ends_with($host, $baseDomain) || $host === $baseDomain) {
                $subdomain = null;
            } else {
                $subdomain = str_replace('.'.$baseDomain, '', $host);
            }

            self::assertSame($case['expected'], $subdomain, "Failed for host: {$case['host']}");
        }
    }
}
