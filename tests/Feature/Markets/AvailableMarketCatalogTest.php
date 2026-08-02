<?php

declare(strict_types=1);

namespace Tests\Feature\Markets;

use App\Application\Markets\AvailableMarketCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AvailableMarketCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_only_the_latest_effective_published_version_with_active_references(): void
    {
        $now = now();
        $packId = (string) Str::uuid();
        $publishedVersionId = (string) Str::uuid();

        DB::table('countries')->insert([
            'code' => 'CA',
            'name' => 'Canada',
            'native_name' => 'Canada',
            'calling_code' => '+1',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('currencies')->insert([
            'code' => 'CAD',
            'name' => 'Canadian dollar',
            'symbol' => '$',
            'minor_unit' => 2,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('locales')->insert([
            'code' => 'en-CA',
            'name' => 'English (Canada)',
            'native_name' => 'English (Canada)',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('timezones')->insert([
            'name' => 'America/Toronto',
            'label' => 'Eastern Time — Toronto',
            'utc_offset' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('market_packs')->insert([
            'id' => $packId,
            'key' => 'ca',
            'country_code' => 'CA',
            'name' => 'Canada',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('market_pack_versions')->insert([
            [
                'id' => $publishedVersionId,
                'market_pack_id' => $packId,
                'version' => 1,
                'status' => 'published',
                'default_locale_code' => 'en-CA',
                'default_currency_code' => 'CAD',
                'default_timezone_name' => 'America/Toronto',
                'supported_locales' => json_encode(['en-CA'], JSON_THROW_ON_ERROR),
                'supported_currencies' => json_encode(['CAD'], JSON_THROW_ON_ERROR),
                'supported_timezones' => json_encode(['America/Toronto'], JSON_THROW_ON_ERROR),
                'capabilities' => json_encode([], JSON_THROW_ON_ERROR),
                'address_schema' => json_encode(['levels' => ['province', 'city']], JSON_THROW_ON_ERROR),
                'effective_from' => $now->copy()->subMinute(),
                'published_at' => $now->copy()->subMinute(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'market_pack_id' => $packId,
                'version' => 2,
                'status' => 'draft',
                'default_locale_code' => 'en-CA',
                'default_currency_code' => 'CAD',
                'default_timezone_name' => 'America/Toronto',
                'supported_locales' => json_encode(['en-CA'], JSON_THROW_ON_ERROR),
                'supported_currencies' => json_encode(['CAD'], JSON_THROW_ON_ERROR),
                'supported_timezones' => json_encode(['America/Toronto'], JSON_THROW_ON_ERROR),
                'capabilities' => json_encode([], JSON_THROW_ON_ERROR),
                'address_schema' => json_encode([], JSON_THROW_ON_ERROR),
                'effective_from' => null,
                'published_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        $areaId = DB::table('administrative_areas')->insertGetId([
            'country_code' => 'CA',
            'parent_id' => null,
            'level' => 1,
            'type' => 'province',
            'code' => 'ON',
            'name' => 'Ontario',
            'slug' => 'ontario',
            'legacy_department_id' => null,
            'legacy_municipality_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $localityId = DB::table('localities')->insertGetId([
            'country_code' => 'CA',
            'administrative_area_id' => $areaId,
            'name' => 'Toronto',
            'slug' => 'toronto',
            'timezone_name' => 'America/Toronto',
            'latitude' => null,
            'longitude' => null,
            'legacy_city_id' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $catalog = app(AvailableMarketCatalog::class)->all();

        self::assertSame([
            [
                'countryCode' => 'CA',
                'countryName' => 'Canada',
                'callingCode' => '+1',
                'marketPackVersionId' => $publishedVersionId,
                'marketPackVersion' => 1,
                'defaults' => [
                    'localeCode' => 'en-CA',
                    'currencyCode' => 'CAD',
                    'timezone' => 'America/Toronto',
                ],
                'locales' => [
                    ['code' => 'en-CA', 'name' => 'English (Canada)', 'nativeName' => 'English (Canada)'],
                ],
                'currencies' => [
                    ['code' => 'CAD', 'name' => 'Canadian dollar', 'symbol' => '$'],
                ],
                'timezones' => [
                    ['name' => 'America/Toronto', 'label' => 'Eastern Time — Toronto'],
                ],
                'localities' => [
                    [
                        'id' => $localityId,
                        'name' => 'Toronto',
                        'administrativeArea' => 'Ontario',
                        'timezone' => 'America/Toronto',
                    ],
                ],
                'addressSchema' => ['levels' => ['province', 'city']],
            ],
        ], $catalog);

        $this->get(route('tenant.register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/RegisterTenant')
                ->where('markets.0.countryCode', 'CA')
                ->where('markets.0.marketPackVersionId', $publishedVersionId)
                ->where('markets.0.defaults.currencyCode', 'CAD')
                ->where('markets.0.localities.0.name', 'Toronto'));
    }

    public function test_it_fails_closed_when_no_published_market_pack_is_complete(): void
    {
        self::assertSame([], app(AvailableMarketCatalog::class)->all());
    }
}
