<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Application\Markets\HondurasMarketBackfill;
use App\Models\User;
use Database\Seeders\DovraRegistrationE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DovraRegistrationE2ESeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_prepares_registration_catalog_category_and_locality(): void
    {
        $this->seedFixture();

        $this->assertDatabaseHas('categories', [
            'slug' => 'restaurantes',
            'name' => 'Restaurantes',
        ]);
        $this->assertDatabaseHas('cities', [
            'slug' => 'tegucigalpa',
            'name' => 'Tegucigalpa',
            'municipality_id' => null,
        ]);

        $cityId = DB::table('cities')->where('slug', 'tegucigalpa')->value('id');

        self::assertNotNull($cityId);
        $this->assertDatabaseHas('localities', [
            'legacy_city_id' => $cityId,
            'country_code' => 'HN',
            'name' => 'Tegucigalpa',
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('market_packs', [
            'key' => 'hn',
            'country_code' => 'HN',
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('market_pack_versions', [
            'version' => 1,
            'status' => 'published',
        ]);

        self::assertSame(0, DB::table('users')->count());
        self::assertSame(0, DB::table('tenants')->count());
        self::assertSame(0, DB::table('products')->count());
    }

    public function test_second_seed_is_idempotent_without_duplicate_reference_rows(): void
    {
        $this->seedFixture();
        $this->seedFixture();

        self::assertSame(1, DB::table('categories')->where('slug', 'restaurantes')->count());
        self::assertSame(1, DB::table('cities')->where('slug', 'tegucigalpa')->count());
        self::assertSame(1, DB::table('localities')->where('legacy_city_id', DB::table('cities')->where('slug', 'tegucigalpa')->value('id'))->count());
        self::assertSame(1, DB::table('countries')->where('code', 'HN')->count());
        self::assertSame(1, DB::table('market_packs')->where('key', 'hn')->count());
        self::assertSame(1, DB::table('market_pack_versions')->where('version', 1)->count());
        self::assertSame(0, DB::table('users')->count());
        self::assertSame(0, DB::table('tenants')->count());
        self::assertSame(0, DB::table('products')->count());
    }

    public function test_seed_fails_closed_outside_local_or_testing_environment(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        try {
            $this->expectException(\LogicException::class);
            $this->expectExceptionMessage('DovraRegistrationE2ESeeder is restricted to local or testing environments.');

            $this->runFixtureSeeder();
        } finally {
            $this->app->detectEnvironment(static fn (): string => 'testing');
        }
    }

    public function test_registration_get_exposes_non_empty_catalog_and_market_props(): void
    {
        $this->seedFixture();
        Cache::flush();

        $this->get('/registrar-negocio')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/RegisterTenant')
                ->has('categories', 1)
                ->where('categories.0.slug', 'restaurantes')
                ->has('cities', 1)
                ->where('cities.0.slug', 'tegucigalpa')
                ->has('markets', 1)
                ->has('markets.0.localities', 1)
            );
    }

    public function test_registration_post_persists_owner_tenant_and_primary_market_location(): void
    {
        $this->seedFixture();

        $categoryId = DB::table('categories')->where('slug', 'restaurantes')->value('id');
        $cityId = DB::table('cities')->where('slug', 'tegucigalpa')->value('id');
        $localityId = DB::table('localities')->where('legacy_city_id', $cityId)->value('id');

        self::assertNotNull($categoryId);
        self::assertNotNull($cityId);
        self::assertNotNull($localityId);

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'text/html, application/xhtml+xml',
        ])->post('/registrar-negocio', [
            'business_name' => 'Dovra Registration E2E',
            'slug' => 'dovra-registration-e2e',
            'category_id' => $categoryId,
            'commercial_mode' => 'products',
            'business_type' => 'retail',
            'rubro_key' => 'retail-core',
            'city_id' => $cityId,
            'country_code' => 'HN',
            'locality_id' => $localityId,
            'locale_code' => 'es-HN',
            'currency_code' => 'HNL',
            'timezone' => 'America/Tegucigalpa',
            'address' => 'Avenida E2E 2026, Tegucigalpa',
            'whatsapp' => '+504 9999-2026',
            'name' => 'Dovra Owner E2E',
            'email' => 'dovra.registration.e2e@example.test',
            'password' => 'DovraE2E!2026',
            'password_confirmation' => 'DovraE2E!2026',
            'selected_plan' => 'free',
            'terms_accepted' => true,
            'privacy_accepted' => true,
            'terms_version' => '2026-05-10',
            'privacy_version' => '2026-05-10',
        ]);

        $response->assertStatus(409)->assertHeader('X-Inertia-Location', '/app/dovra-registration-e2e');

        $owner = User::query()->where('email', 'dovra.registration.e2e@example.test')->firstOrFail();
        $tenant = DB::table('tenants')->where('slug', 'dovra-registration-e2e')->first();

        self::assertNotNull($tenant);
        self::assertSame((string) $owner->id, (string) $tenant->user_id);
        self::assertSame((string) $tenant->id, (string) $owner->tenant_id);
        $this->assertAuthenticatedAs($owner);

        $this->assertDatabaseHas('tenant_markets', [
            'tenant_id' => $tenant->id,
            'country_code' => 'HN',
            'locale_code' => 'es-HN',
            'currency_code' => 'HNL',
            'timezone_name' => 'America/Tegucigalpa',
            'is_primary' => 1,
        ]);
        $this->assertDatabaseHas('locations', [
            'tenant_id' => $tenant->id,
            'country_code' => 'HN',
            'locality_id' => $localityId,
            'name' => 'Dovra Registration E2E',
            'address' => 'Avenida E2E 2026, Tegucigalpa',
            'is_primary' => 1,
        ]);
    }

    private function seedFixture(): void
    {
        $this->runFixtureSeeder();
        Cache::flush();
    }

    private function runFixtureSeeder(): void
    {
        $this->app->make(DovraRegistrationE2ESeeder::class)
            ->run($this->app->make(HondurasMarketBackfill::class));
    }
}
