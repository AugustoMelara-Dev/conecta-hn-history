<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Availability\AvailabilityRuleWriter;
use App\Application\Availability\Commands\ReplaceAvailabilityRulesCommand;
use App\Application\Markets\HondurasMarketBackfill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Guarded, deterministic fixture for the real public appointment journey.
 *
 * This fixture is local/testing-only and intentionally writes no payment,
 * subscription, billing, order, or customer transaction rows. The service and
 * provider records are the canonical catalog rows consumed by public booking;
 * availability is always replaced through AvailabilityRuleWriter.
 */
final class DovraBookingConversionE2ESeeder extends Seeder
{
    private const TENANT_SLUG = 'dovra-booking-conversion-e2e';

    private const OWNER_EMAIL = 'dovra.booking.conversion.e2e@example.test';

    private const SERVICE_NAME = 'Servicio Booking E2E';

    private const PROVIDER_NAME = 'Provider Booking E2E';

    private const MARKET_COUNTRY = 'HN';

    private const MARKET_LOCALE = 'es-HN';

    private const MARKET_CURRENCY = 'HNL';

    private const MARKET_TIMEZONE = 'America/Tegucigalpa';

    private const SERVICE_PRICE = '350.00';

    private const SERVICE_PRICE_MINOR = 35000;

    public function run(
        HondurasMarketBackfill $backfill,
        AvailabilityRuleWriter $availability,
    ): void {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException(
                'DovraBookingConversionE2ESeeder is restricted to local or testing environments.',
            );
        }

        if (getenv('DOVRA_E2E_ALLOW_SYNTHETIC_BOOKING_CONVERSION') !== 'true') {
            throw new \LogicException(
                'DovraBookingConversionE2ESeeder requires DOVRA_E2E_ALLOW_SYNTHETIC_BOOKING_CONVERSION=true.',
            );
        }

        $this->assertCanonicalSchema();

        DB::transaction(function () use ($backfill, $availability): void {
            $now = now();
            $categoryId = $this->category($now);
            $cityId = $this->city($now);
            $ownerId = $this->owner($now);
            $tenantId = $this->tenant($ownerId, $categoryId, $cityId, $now);

            DB::table('users')
                ->where('id', $ownerId)
                ->update($this->onlyExistingColumns('users', [
                    'tenant_id' => $tenantId,
                    'updated_at' => $now,
                ]));

            // Canonical Honduras market data and the tenant's primary location
            // are established before catalog pivots and availability.
            $backfill->run();
            $this->market($tenantId, $now);
            $locationId = $this->location($tenantId, $cityId, $now);
            $serviceId = $this->service($tenantId, $now);
            $providerId = $this->provider($tenantId, $now);
            $this->pivots($tenantId, $serviceId, $providerId, $locationId, $now);

            $futureDate = now(self::MARKET_TIMEZONE)->addDay()->format('Y-m-d');
            $availability->replace(new ReplaceAvailabilityRulesCommand(
                tenantId: $tenantId,
                actorId: $ownerId,
                idempotencyKey: 'dovra-booking-conversion:'.$futureDate,
                locationId: $locationId,
                serviceId: $serviceId,
                providerId: $providerId,
                rules: array_map(
                    static fn (int $weekday): array => [
                        'weekday' => $weekday,
                        'starts_at_local' => '09:00',
                        'ends_at_local' => '17:00',
                        'effective_from' => now(self::MARKET_TIMEZONE)->format('Y-m-d'),
                        'effective_until' => null,
                    ],
                    range(1, 7),
                ),
                exceptions: [[
                    'local_date' => $futureDate,
                    'kind' => 'open',
                    'starts_at_local' => '09:00',
                    'ends_at_local' => '17:00',
                    'reason' => 'Synthetic booking conversion slot',
                ]],
            ));
        });
    }

    private function assertCanonicalSchema(): void
    {
        foreach ([
            'users',
            'tenants',
            'services',
            'service_providers',
            'locations',
            'service_locations',
            'service_provider_locations',
            'availability_rules',
            'availability_exceptions',
            'availability_write_operations',
            'tenant_markets',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new \LogicException("{$table} canonical booking table is required.");
            }
        }
    }

    private function category(mixed $now): ?int
    {
        if (! Schema::hasTable('categories')) {
            return null;
        }

        DB::table('categories')->updateOrInsert(
            ['slug' => 'servicios-profesionales'],
            $this->onlyExistingColumns('categories', [
                'name' => 'Servicios profesionales',
                'icon' => 'calendar-clock',
                'color' => '#0f766e',
                'is_active' => true,
                'is_featured' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return (int) DB::table('categories')->where('slug', 'servicios-profesionales')->value('id');
    }

    private function city(mixed $now): ?int
    {
        if (! Schema::hasTable('cities')) {
            return null;
        }

        DB::table('cities')->updateOrInsert(
            ['slug' => 'tegucigalpa'],
            $this->onlyExistingColumns('cities', [
                'name' => 'Tegucigalpa',
                'municipality_id' => null,
                'is_active' => true,
                'is_featured' => true,
                'coordinates' => json_encode([
                    'lat' => 14.0723,
                    'lng' => -87.1921,
                ], JSON_THROW_ON_ERROR),
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return (int) DB::table('cities')->where('slug', 'tegucigalpa')->value('id');
    }

    private function owner(mixed $now): string
    {
        $existing = DB::table('users')->where('email', self::OWNER_EMAIL)->first();
        $ownerId = (string) ($existing->id ?? Str::uuid());

        DB::table('users')->updateOrInsert(
            ['email' => self::OWNER_EMAIL],
            $this->onlyExistingColumns('users', [
                'id' => $ownerId,
                'name' => 'Booking E2E Owner',
                'password' => Hash::make('password'),
                'role' => 'seller',
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return $ownerId;
    }

    private function tenant(
        string $ownerId,
        ?int $categoryId,
        ?int $cityId,
        mixed $now,
    ): string {
        $existing = DB::table('tenants')->where('slug', self::TENANT_SLUG)->first();
        $tenantId = (string) ($existing->id ?? Str::uuid());

        DB::table('tenants')->updateOrInsert(
            ['slug' => self::TENANT_SLUG],
            $this->onlyExistingColumns('tenants', [
                'id' => $tenantId,
                'name' => 'Dovra Booking Conversion E2E',
                'description' => 'Synthetic appointment booking fixture for local verification.',
                'user_id' => $ownerId,
                'category_id' => $categoryId,
                'city_id' => $cityId,
                'status' => 'approved',
                'verification_status' => 'verified',
                'verified_at' => $now,
                'is_active' => true,
                'is_paused' => false,
                'publication_paused_at' => null,
                'plan' => 'PRO',
                'plan_type' => 'pro',
                'subscription_status' => 'active',
                'subscription_ends_at' => null,
                'is_pro' => true,
                'product_limit' => 100,
                'business_type' => 'service',
                'commercial_mode' => 'appointments',
                'rubro_key' => 'service-booking',
                'tags' => json_encode(['booking-e2e', 'honduras'], JSON_THROW_ON_ERROR),
                'whatsapp_number' => null,
                'phone' => null,
                'email' => self::OWNER_EMAIL,
                'address' => 'Avenida Booking E2E 2026, Tegucigalpa',
                'payment_methods' => json_encode([], JSON_THROW_ON_ERROR),
                'brand_color' => '#0f766e',
                'primary_color' => '#0f766e',
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return $tenantId;
    }

    private function market(string $tenantId, mixed $now): void
    {
        $versionId = DB::table('market_pack_versions')
            ->join('market_packs', 'market_packs.id', '=', 'market_pack_versions.market_pack_id')
            ->where('market_packs.key', 'hn')
            ->where('market_pack_versions.version', 1)
            ->value('market_pack_versions.id');
        if (! is_string($versionId) || $versionId === '') {
            throw new \LogicException('Honduras market pack version is required for booking fixture.');
        }

        DB::table('tenant_markets')->updateOrInsert(
            ['tenant_id' => $tenantId],
            $this->onlyExistingColumns('tenant_markets', [
                'id' => (string) (DB::table('tenant_markets')->where('tenant_id', $tenantId)->value('id') ?: Str::uuid()),
                'market_pack_version_id' => $versionId,
                'country_code' => self::MARKET_COUNTRY,
                'locale_code' => self::MARKET_LOCALE,
                'currency_code' => self::MARKET_CURRENCY,
                'timezone_name' => self::MARKET_TIMEZONE,
                'is_primary' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );
    }

    private function location(string $tenantId, ?int $cityId, mixed $now): string
    {
        $localityId = $cityId === null || ! Schema::hasTable('localities')
            ? null
            : DB::table('localities')->where('legacy_city_id', $cityId)->value('id');
        $administrativeAreaId = $localityId === null || ! Schema::hasTable('localities')
            ? null
            : DB::table('localities')->where('id', $localityId)->value('administrative_area_id');
        $existing = DB::table('locations')
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->where('slug', 'primary')->orWhere('is_primary', true);
            })
            ->orderByDesc('is_primary')
            ->first();
        $locationId = (string) ($existing->id ?? Str::uuid());

        DB::table('locations')->updateOrInsert(
            ['id' => $locationId],
            $this->onlyExistingColumns('locations', [
                'tenant_id' => $tenantId,
                'country_code' => self::MARKET_COUNTRY,
                'administrative_area_id' => $administrativeAreaId,
                'locality_id' => $localityId,
                'locale_code' => self::MARKET_LOCALE,
                'currency_code' => self::MARKET_CURRENCY,
                'timezone_name' => self::MARKET_TIMEZONE,
                'name' => 'Dovra Booking Conversion E2E',
                'slug' => 'primary',
                'address' => 'Avenida Booking E2E 2026, Tegucigalpa',
                'phone' => null,
                'contact_name' => null,
                'hours_data' => null,
                'is_active' => true,
                'is_primary' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return $locationId;
    }

    private function service(string $tenantId, mixed $now): int
    {
        DB::table('services')->updateOrInsert(
            ['tenant_id' => $tenantId, 'name' => self::SERVICE_NAME],
            $this->onlyExistingColumns('services', [
                'description' => 'Servicio reservable para la conversión E2E.',
                'duration_minutes' => 30,
                'price' => self::SERVICE_PRICE,
                'price_minor' => self::SERVICE_PRICE_MINOR,
                'currency' => self::MARKET_CURRENCY,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return (int) DB::table('services')
            ->where('tenant_id', $tenantId)
            ->where('name', self::SERVICE_NAME)
            ->value('id');
    }

    private function provider(string $tenantId, mixed $now): int
    {
        DB::table('service_providers')->updateOrInsert(
            ['tenant_id' => $tenantId, 'name' => self::PROVIDER_NAME],
            $this->onlyExistingColumns('service_providers', [
                'email' => 'provider.booking.e2e@example.test',
                'phone' => null,
                'schedule_config' => null,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]),
        );

        return (int) DB::table('service_providers')
            ->where('tenant_id', $tenantId)
            ->where('name', self::PROVIDER_NAME)
            ->value('id');
    }

    private function pivots(
        string $tenantId,
        int $serviceId,
        int $providerId,
        string $locationId,
        mixed $now,
    ): void {
        DB::table('service_locations')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'service_id' => $serviceId,
                'location_id' => $locationId,
            ],
            ['updated_at' => $now, 'created_at' => $now],
        );
        DB::table('service_provider_locations')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'service_provider_id' => $providerId,
                'location_id' => $locationId,
            ],
            ['updated_at' => $now, 'created_at' => $now],
        );
    }

    /** @param array<string, mixed> $attributes */
    private function onlyExistingColumns(string $table, array $attributes): array
    {
        return array_filter(
            $attributes,
            static fn (mixed $value, string $column): bool => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
