<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GlobalMarketFixture
{
    public static function assignCanada(Tenant $tenant): Location
    {
        $now = now();
        DB::table('countries')->updateOrInsert(
            ['code' => 'CA'],
            [
                'name' => 'Canada',
                'native_name' => 'Canada',
                'calling_code' => '+1',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('currencies')->updateOrInsert(
            ['code' => 'CAD'],
            [
                'name' => 'Canadian Dollar',
                'symbol' => '$',
                'minor_unit' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('locales')->updateOrInsert(
            ['code' => 'en-CA'],
            [
                'name' => 'English (Canada)',
                'native_name' => 'English (Canada)',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('timezones')->updateOrInsert(
            ['name' => 'America/Toronto'],
            [
                'label' => 'Eastern Time — Toronto',
                'utc_offset' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $packId = (string) (DB::table('market_packs')->where('key', 'ca')->value('id') ?: Str::uuid());
        DB::table('market_packs')->updateOrInsert(
            ['key' => 'ca'],
            [
                'id' => $packId,
                'country_code' => 'CA',
                'name' => 'Canada',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $versionId = (string) (DB::table('market_pack_versions')
            ->where('market_pack_id', $packId)
            ->where('version', 1)
            ->value('id') ?: Str::uuid());
        DB::table('market_pack_versions')->updateOrInsert(
            ['market_pack_id' => $packId, 'version' => 1],
            [
                'id' => $versionId,
                'status' => 'published',
                'default_locale_code' => 'en-CA',
                'default_currency_code' => 'CAD',
                'default_timezone_name' => 'America/Toronto',
                'supported_locales' => json_encode(['en-CA'], JSON_THROW_ON_ERROR),
                'supported_currencies' => json_encode(['CAD'], JSON_THROW_ON_ERROR),
                'supported_timezones' => json_encode(['America/Toronto'], JSON_THROW_ON_ERROR),
                'capabilities' => json_encode([], JSON_THROW_ON_ERROR),
                'address_schema' => json_encode(['levels' => ['province', 'city']], JSON_THROW_ON_ERROR),
                'effective_from' => $now,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('tenant_markets')->updateOrInsert(
            ['tenant_id' => $tenant->id],
            [
                'id' => (string) (DB::table('tenant_markets')->where('tenant_id', $tenant->id)->value('id') ?: Str::uuid()),
                'market_pack_version_id' => $versionId,
                'country_code' => 'CA',
                'locale_code' => 'en-CA',
                'currency_code' => 'CAD',
                'timezone_name' => 'America/Toronto',
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $locationId = (string) Str::uuid();
        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenant->id,
            'country_code' => 'CA',
            'administrative_area_id' => null,
            'locality_id' => null,
            'locale_code' => 'en-CA',
            'currency_code' => 'CAD',
            'timezone_name' => 'America/Toronto',
            'name' => 'Toronto',
            'slug' => 'toronto-'.substr($locationId, 0, 8),
            'address' => '100 King Street',
            'phone' => null,
            'contact_name' => null,
            'hours_data' => null,
            'is_active' => true,
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Location::query()->withoutGlobalScopes()->findOrFail($locationId);
    }

    public static function assignJapan(Tenant $tenant): Location
    {
        $now = now();
        DB::table('countries')->updateOrInsert(
            ['code' => 'JP'],
            [
                'name' => 'Japan',
                'native_name' => '日本',
                'calling_code' => '+81',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('currencies')->updateOrInsert(
            ['code' => 'JPY'],
            [
                'name' => 'Japanese Yen',
                'symbol' => '¥',
                'minor_unit' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('locales')->updateOrInsert(
            ['code' => 'ja-JP'],
            [
                'name' => 'Japanese (Japan)',
                'native_name' => '日本語 (日本)',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('timezones')->updateOrInsert(
            ['name' => 'Asia/Tokyo'],
            [
                'label' => 'Japan Standard Time — Tokyo',
                'utc_offset' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $packId = (string) (DB::table('market_packs')->where('key', 'jp')->value('id') ?: Str::uuid());
        DB::table('market_packs')->updateOrInsert(
            ['key' => 'jp'],
            [
                'id' => $packId,
                'country_code' => 'JP',
                'name' => 'Japan',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        $versionId = (string) (DB::table('market_pack_versions')
            ->where('market_pack_id', $packId)
            ->where('version', 1)
            ->value('id') ?: Str::uuid());
        DB::table('market_pack_versions')->updateOrInsert(
            ['market_pack_id' => $packId, 'version' => 1],
            [
                'id' => $versionId,
                'status' => 'published',
                'default_locale_code' => 'ja-JP',
                'default_currency_code' => 'JPY',
                'default_timezone_name' => 'Asia/Tokyo',
                'supported_locales' => json_encode(['ja-JP'], JSON_THROW_ON_ERROR),
                'supported_currencies' => json_encode(['JPY'], JSON_THROW_ON_ERROR),
                'supported_timezones' => json_encode(['Asia/Tokyo'], JSON_THROW_ON_ERROR),
                'capabilities' => json_encode([], JSON_THROW_ON_ERROR),
                'address_schema' => json_encode(['levels' => ['prefecture', 'city']], JSON_THROW_ON_ERROR),
                'effective_from' => $now,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
        DB::table('tenant_markets')->updateOrInsert(
            ['tenant_id' => $tenant->id],
            [
                'id' => (string) (DB::table('tenant_markets')->where('tenant_id', $tenant->id)->value('id') ?: Str::uuid()),
                'market_pack_version_id' => $versionId,
                'country_code' => 'JP',
                'locale_code' => 'ja-JP',
                'currency_code' => 'JPY',
                'timezone_name' => 'Asia/Tokyo',
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $locationId = (string) Str::uuid();
        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenant->id,
            'country_code' => 'JP',
            'administrative_area_id' => null,
            'locality_id' => null,
            'locale_code' => 'ja-JP',
            'currency_code' => 'JPY',
            'timezone_name' => 'Asia/Tokyo',
            'name' => 'Tokyo',
            'slug' => 'tokyo-'.substr($locationId, 0, 8),
            'address' => '1-1 Marunouchi',
            'phone' => null,
            'contact_name' => null,
            'hours_data' => null,
            'is_active' => true,
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Location::query()->withoutGlobalScopes()->findOrFail($locationId);
    }
}
