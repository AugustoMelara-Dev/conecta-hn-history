<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Tenant;
use Database\Seeders\ProductionShowcaseTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductionShowcaseTenantSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_expected_storefront_tenants(): void
    {
        $this->seed(ProductionShowcaseTenantSeeder::class);

        $expectedSlugs = [
            'casa-brasa-bistro',
            'patio-casual-bistro',
            'alto-brew-cafeteria',
            'movil-center-hn',
            'titan-flagship-mobile',
            'ferreteria-ferremax-hn',
            'bufete-lex-hn',
            'lex-corporativo-hn',
            'clinica-salud-vital',
            'clinica-dental-sonrisa',
            'faro-growth-advisory',
            'faro-finanzas-advisory',
            'barberia-norte-pro',
            'district-barber-lounge',
            'aura-spa-studio',
            'taller-motor-pro',
            'repuestos-turbo-center',
        ];

        foreach ($expectedSlugs as $slug) {
            $tenant = Tenant::query()->withoutGlobalScopes()->where('slug', $slug)->first();

            $this->assertNotNull($tenant, "Missing seeded tenant for slug [$slug].");
            $this->assertSame('approved', $tenant->status, "Tenant [$slug] is not approved.");
            $this->assertTrue((bool) $tenant->is_active, "Tenant [$slug] is not active.");
        }
    }
}
