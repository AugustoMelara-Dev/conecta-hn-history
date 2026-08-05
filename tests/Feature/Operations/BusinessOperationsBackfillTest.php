<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Operations\BusinessOperationsBackfill;
use App\Models\Appointment;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class BusinessOperationsBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_maps_explicit_market_location_schedule_and_reservation_snapshots_idempotently(): void
    {
        ['version_id' => $versionId] = $this->seedCanadaMarket();
        $tenant = Tenant::factory()->create(['city_id' => null]);
        $locationId = $this->assignCanadaMarketAndLocation($tenant, $versionId);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Legacy consultation',
            'duration_minutes' => 45,
            'price' => '125.50',
        ]);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'price' => '25.75',
        ]);
        $provider = ServiceProvider::factory()->withSchedule([
            'monday' => ['09:00-12:00', '13:00-17:00'],
        ])->create(['tenant_id' => $tenant->id]);
        $appointment = Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'service_provider_id' => $provider->id,
            'scheduled_at' => '2026-01-15 15:00:00',
            'end_at' => '2026-01-15 15:45:00',
        ]);

        $first = app(BusinessOperationsBackfill::class)->run();
        $second = app(BusinessOperationsBackfill::class)->run();

        self::assertSame('CAD', DB::table('services')->where('id', $service->id)->value('currency'));
        self::assertSame(12550, DB::table('services')->where('id', $service->id)->value('price_minor'));
        self::assertSame('CAD', DB::table('products')->where('id', $product->id)->value('currency'));
        self::assertSame(2575, DB::table('products')->where('id', $product->id)->value('price_minor'));
        $this->assertDatabaseHas('service_locations', [
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'location_id' => $locationId,
        ]);
        $this->assertDatabaseHas('service_provider_locations', [
            'tenant_id' => $tenant->id,
            'service_provider_id' => $provider->id,
            'location_id' => $locationId,
        ]);
        self::assertSame(2, DB::table('availability_rules')
            ->where('tenant_id', $tenant->id)
            ->where('service_provider_id', $provider->id)
            ->count());
        $this->assertDatabaseHas('availability_rules', [
            'tenant_id' => $tenant->id,
            'location_id' => $locationId,
            'service_provider_id' => $provider->id,
            'weekday' => 1,
            'starts_at_local' => '09:00:00',
            'ends_at_local' => '12:00:00',
            'timezone' => 'America/Toronto',
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'location_id' => $locationId,
            'service_name_snapshot' => 'Legacy consultation',
            'duration_minutes' => 45,
            'currency' => 'CAD',
            'price_minor' => 12550,
            'timezone' => 'America/Toronto',
            'local_date' => '2026-01-15',
            'source' => 'legacy_backfill',
        ]);

        self::assertSame(1, $first->servicesMapped);
        self::assertSame(1, $first->productsMapped);
        self::assertSame(1, $first->providersMapped);
        self::assertSame(1, $first->appointmentsMapped);
        self::assertSame(2, $first->availabilityRulesTotal);
        self::assertSame($first->toArray(), $second->toArray());
    }

    public function test_backfill_reports_invalid_schedules_without_granting_availability(): void
    {
        ['version_id' => $versionId] = $this->seedCanadaMarket();
        $tenant = Tenant::factory()->create(['city_id' => null]);
        $this->assignCanadaMarketAndLocation($tenant, $versionId);
        $provider = ServiceProvider::factory()->withSchedule([
            'monday' => ['not-a-time-range'],
        ])->create(['tenant_id' => $tenant->id]);

        $report = app(BusinessOperationsBackfill::class)->run();

        self::assertSame(0, DB::table('availability_rules')
            ->where('tenant_id', $tenant->id)
            ->where('service_provider_id', $provider->id)
            ->count());
        self::assertContains('provider_schedule_invalid', array_column($report->gaps, 'code'));
    }

    public function test_backfill_never_invents_market_or_location_context(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => null]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]);

        $report = app(BusinessOperationsBackfill::class)->run();

        self::assertNull(DB::table('services')->where('id', $service->id)->value('currency'));
        self::assertSame(0, DB::table('service_locations')->where('tenant_id', $tenant->id)->count());
        self::assertContains('tenant_market_missing', array_column($report->gaps, 'code'));
        self::assertContains('tenant_location_missing', array_column($report->gaps, 'code'));
    }

    /**
     * @return array{version_id: string}
     */
    private function seedCanadaMarket(): array
    {
        $now = now();
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
            'name' => 'Canadian Dollar',
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

        $packId = (string) Str::uuid();
        $versionId = (string) Str::uuid();
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
            'id' => $versionId,
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
            'effective_from' => $now,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['version_id' => $versionId];
    }

    private function assignCanadaMarketAndLocation(Tenant $tenant, string $versionId): string
    {
        $now = now();
        DB::table('tenant_markets')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'market_pack_version_id' => $versionId,
            'country_code' => 'CA',
            'locale_code' => 'en-CA',
            'currency_code' => 'CAD',
            'timezone_name' => 'America/Toronto',
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
            'slug' => 'toronto',
            'address' => '100 King Street',
            'phone' => null,
            'contact_name' => null,
            'hours_data' => null,
            'is_active' => true,
            'is_primary' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $locationId;
    }
}
