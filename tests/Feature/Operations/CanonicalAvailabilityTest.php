<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Availability\AvailabilityQuery;
use App\Application\Availability\AvailabilityRuleWriter;
use App\Application\Availability\Commands\ReplaceAvailabilityRulesCommand;
use App\Domain\Access\Enums\Role;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Booking\HttpPublicBookingResponder;
use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class CanonicalAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_replaces_rules_and_exceptions_idempotently_with_location_timezone(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();
        $writer = app(AvailabilityRuleWriter::class);
        $command = new ReplaceAvailabilityRulesCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'availability:replace:1',
            locationId: (string) $location->id,
            serviceId: (int) $service->id,
            providerId: (int) $provider->id,
            rules: [[
                'weekday' => 1,
                'starts_at_local' => '09:00',
                'ends_at_local' => '12:00',
            ]],
            exceptions: [[
                'local_date' => '2026-08-03',
                'kind' => 'closed',
                'reason' => 'Civic holiday',
            ]],
        );

        $writer->replace($command);
        $writer->replace($command);

        self::assertSame(1, DB::table('availability_rules')->where('source', 'canonical')->count());
        self::assertSame(1, DB::table('availability_exceptions')->where('source', 'canonical')->count());
        self::assertSame('America/Toronto', DB::table('availability_rules')->value('timezone'));
        self::assertSame(1, DB::table('availability_write_operations')->count());

        $this->expectException(\LogicException::class);
        $writer->replace(new ReplaceAvailabilityRulesCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'availability:replace:1',
            locationId: (string) $location->id,
            serviceId: (int) $service->id,
            providerId: (int) $provider->id,
            rules: [[
                'weekday' => 1,
                'starts_at_local' => '10:00',
                'ends_at_local' => '12:00',
            ]],
            exceptions: [],
        ));
    }

    public function test_slots_apply_closed_dates_and_subtract_active_provider_overlaps(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();
        $this->replace($tenant, $owner, $location->id, $service->id, $provider->id, [
            ['weekday' => 1, 'starts_at_local' => '09:00', 'ends_at_local' => '12:00'],
            ['weekday' => 2, 'starts_at_local' => '09:00', 'ends_at_local' => '12:00'],
        ], [[
            'local_date' => '2026-08-03',
            'kind' => 'closed',
            'reason' => 'Closed',
        ]]);
        Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'service_provider_id' => $provider->id,
            'location_id' => $location->id,
            'scheduled_at' => CarbonImmutable::parse('2026-08-04 14:00:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-08-04 15:00:00', 'UTC'),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $query = app(AvailabilityQuery::class);
        self::assertSame([], $query->slots(
            (string) $tenant->id,
            (int) $service->id,
            (int) $provider->id,
            (string) $location->id,
            CarbonImmutable::parse('2026-08-03', 'America/Toronto'),
        ));
        $slots = $query->slots(
            (string) $tenant->id,
            (int) $service->id,
            (int) $provider->id,
            (string) $location->id,
            CarbonImmutable::parse('2026-08-04', 'America/Toronto'),
        );

        self::assertSame(['09:00', '11:00'], array_column($slots, 'time'));
        self::assertSame(
            '2026-08-04T13:00:00+00:00',
            $slots[0]['starts_at_utc'],
        );
    }

    public function test_provider_rules_override_general_rules_and_references_are_tenant_scoped(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();
        $this->replace($tenant, $owner, $location->id, $service->id, null, [
            ['weekday' => 3, 'starts_at_local' => '09:00', 'ends_at_local' => '17:00'],
        ]);
        $this->replace($tenant, $owner, $location->id, $service->id, $provider->id, [
            ['weekday' => 3, 'starts_at_local' => '12:00', 'ends_at_local' => '14:00'],
        ]);
        $query = app(AvailabilityQuery::class);

        self::assertSame(['12:00', '13:00'], array_column($query->slots(
            (string) $tenant->id,
            (int) $service->id,
            (int) $provider->id,
            (string) $location->id,
            CarbonImmutable::parse('2026-08-05', 'America/Toronto'),
        ), 'time'));
        self::assertSame(
            ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'],
            array_column($query->slots(
                (string) $tenant->id,
                (int) $service->id,
                null,
                (string) $location->id,
                CarbonImmutable::parse('2026-08-05', 'America/Toronto'),
            ), 'time'),
        );

        [$other, , , , $foreignProvider] = $this->catalog();
        self::assertNotSame($tenant->id, $other->id);
        $this->expectException(ModelNotFoundException::class);
        $query->slots(
            (string) $tenant->id,
            (int) $service->id,
            (int) $foreignProvider->id,
            (string) $location->id,
            CarbonImmutable::parse('2026-08-05', 'America/Toronto'),
        );
    }

    public function test_toronto_dst_skips_nonexistent_and_ambiguous_wall_times_but_accepts_explicit_offset(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog([
            'duration_minutes' => 30,
        ]);
        $this->replace($tenant, $owner, $location->id, $service->id, $provider->id, [
            ['weekday' => 7, 'starts_at_local' => '00:00', 'ends_at_local' => '04:00'],
        ]);
        $query = app(AvailabilityQuery::class);

        $spring = $query->slots(
            (string) $tenant->id,
            (int) $service->id,
            (int) $provider->id,
            (string) $location->id,
            CarbonImmutable::parse('2026-03-08', 'America/Toronto'),
        );
        self::assertSame(['00:00', '00:30', '01:00', '01:30', '03:00', '03:30'], array_column($spring, 'time'));

        $fall = $query->slots(
            (string) $tenant->id,
            (int) $service->id,
            (int) $provider->id,
            (string) $location->id,
            CarbonImmutable::parse('2026-11-01', 'America/Toronto'),
        );
        self::assertSame(['00:00', '00:30', '02:00', '02:30', '03:00', '03:30'], array_column($fall, 'time'));

        $explicitStart = CarbonImmutable::parse('2026-11-01T01:30:00-04:00')->utc();
        $decision = $query->assertAvailable(
            (string) $tenant->id,
            (int) $service->id,
            (int) $provider->id,
            (string) $location->id,
            $explicitStart,
            $explicitStart->addMinutes(30),
        );
        self::assertTrue($decision->available, $decision->reason);
    }

    public function test_legacy_rules_are_shadow_read_only_until_a_canonical_scope_exists(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();
        DB::table('availability_rules')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'service_id' => $service->id,
            'service_provider_id' => $provider->id,
            'weekday' => 4,
            'starts_at_local' => '09:00',
            'ends_at_local' => '10:00',
            'timezone' => 'America/Toronto',
            'effective_from' => null,
            'effective_until' => null,
            'is_active' => true,
            'source' => 'legacy_provider_schedule',
            'source_key' => 'legacy-test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $query = app(AvailabilityQuery::class);
        $date = CarbonImmutable::parse('2026-08-06', 'America/Toronto');
        self::assertSame(['09:00'], array_column($query->slots(
            (string) $tenant->id,
            (int) $service->id,
            (int) $provider->id,
            (string) $location->id,
            $date,
        ), 'time'));

        $this->replace($tenant, $owner, $location->id, $service->id, $provider->id, [
            ['weekday' => 4, 'starts_at_local' => '12:00', 'ends_at_local' => '13:00'],
        ]);
        self::assertSame(['12:00'], array_column($query->slots(
            (string) $tenant->id,
            (int) $service->id,
            (int) $provider->id,
            (string) $location->id,
            $date,
        ), 'time'));
    }

    public function test_location_timezone_controls_honduras_utc_conversion_without_a_global_default(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();
        DB::table('timezones')->updateOrInsert(
            ['name' => 'America/Tegucigalpa'],
            [
                'label' => 'Central Time — Tegucigalpa',
                'utc_offset' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        DB::table('locations')->where('id', $location->id)->update([
            'timezone_name' => 'America/Tegucigalpa',
        ]);
        $this->replace($tenant, $owner, $location->id, $service->id, $provider->id, [
            ['weekday' => 5, 'starts_at_local' => '09:00', 'ends_at_local' => '10:00'],
        ]);

        $slots = app(AvailabilityQuery::class)->slots(
            (string) $tenant->id,
            (int) $service->id,
            (int) $provider->id,
            (string) $location->id,
            CarbonImmutable::parse('2026-08-07', 'America/Tegucigalpa'),
        );

        self::assertSame('America/Tegucigalpa', $slots[0]['timezone']);
        self::assertSame('2026-08-07T15:00:00+00:00', $slots[0]['starts_at_utc']);
    }

    public function test_public_slots_responder_preserves_shape_and_uses_canonical_availability(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();
        $this->replace($tenant, $owner, $location->id, $service->id, $provider->id, [
            ['weekday' => 5, 'starts_at_local' => '09:00', 'ends_at_local' => '11:00'],
        ]);

        app(TenantContext::class)->setTenant((string) $tenant->id);
        app()->instance('current_tenant', $tenant);
        $request = Request::create('/book/slots', 'GET', [
            'date' => '2026-08-07',
            'service_id' => $service->id,
            'provider_id' => $provider->id,
            'location_id' => $location->id,
        ]);
        Carbon::setTestNow('2026-07-28 12:00:00');
        try {
            $response = app(HttpPublicBookingResponder::class)->getSlots($request);
        } finally {
            Carbon::setTestNow();
            app(TenantContext::class)->reset();
            app()->forgetInstance('current_tenant');
        }

        self::assertSame(200, $response->status());
        $payload = $response->getData(true);
        self::assertSame('09:00', $payload['slots'][0]['time']);
        self::assertSame('09:00 AM', $payload['slots'][0]['display']);
        self::assertSame('America/Toronto', $payload['slots'][0]['timezone']);
        self::assertSame('2026-08-07T13:00:00+00:00', $payload['slots'][0]['starts_at_utc']);
    }

    public function test_tenant_and_provider_expose_canonical_availability_relationships(): void
    {
        $tenant = new Tenant;
        $provider = new ServiceProvider;

        self::assertSame(
            AvailabilityRule::class,
            $tenant->availabilityRules()->getRelated()::class,
        );
        self::assertSame(
            AvailabilityException::class,
            $tenant->availabilityExceptions()->getRelated()::class,
        );
        self::assertSame(
            AvailabilityRule::class,
            $provider->availabilityRules()->getRelated()::class,
        );
        self::assertSame(
            AvailabilityException::class,
            $provider->availabilityExceptions()->getRelated()::class,
        );
    }

    public function test_writer_rejects_client_timezone_and_unknown_rule_fields(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();

        $this->expectException(ValidationException::class);
        app(AvailabilityRuleWriter::class)->replace(new ReplaceAvailabilityRulesCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'availability:unknown-fields',
            locationId: (string) $location->id,
            serviceId: (int) $service->id,
            providerId: (int) $provider->id,
            rules: [[
                'weekday' => 1,
                'starts_at_local' => '09:00',
                'ends_at_local' => '10:00',
                'timezone' => 'UTC',
            ]],
            exceptions: [],
        ));
    }

    public function test_writer_rejects_an_actor_without_staff_management_authority(): void
    {
        [$tenant, , $location, $service, $provider] = $this->catalog();
        $viewer = User::factory()->create();
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $viewer->id,
            'role' => Role::VIEWER->value,
            'preset_key' => null,
            'granted_permissions' => json_encode([], JSON_THROW_ON_ERROR),
            'status' => 'active',
            'invited_at' => now(),
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        app(AvailabilityRuleWriter::class)->replace(new ReplaceAvailabilityRulesCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $viewer->id,
            idempotencyKey: 'availability:viewer-denied',
            locationId: (string) $location->id,
            serviceId: (int) $service->id,
            providerId: (int) $provider->id,
            rules: [[
                'weekday' => 1,
                'starts_at_local' => '09:00',
                'ends_at_local' => '10:00',
            ]],
            exceptions: [],
        ));
    }

    /**
     * @param  array<string, mixed>  $serviceAttributes
     * @return array{Tenant, User, Location, Service, ServiceProvider}
     */
    private function catalog(array $serviceAttributes = []): array
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'service',
            'commercial_mode' => 'appointments',
        ]);
        $location = GlobalMarketFixture::assignCanada($tenant);
        $service = Service::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'duration_minutes' => 60,
            'currency' => 'CAD',
            'price_minor' => 10_000,
        ], $serviceAttributes));
        $provider = ServiceProvider::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('service_locations')->insert([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'location_id' => $location->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('service_provider_locations')->insert([
            'tenant_id' => $tenant->id,
            'service_provider_id' => $provider->id,
            'location_id' => $location->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $owner, $location, $service, $provider];
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @param  list<array<string, mixed>>  $exceptions
     */
    private function replace(
        Tenant $tenant,
        User $actor,
        string $locationId,
        int $serviceId,
        ?int $providerId,
        array $rules,
        array $exceptions = [],
    ): void {
        app(AvailabilityRuleWriter::class)->replace(new ReplaceAvailabilityRulesCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $actor->id,
            idempotencyKey: 'availability:'.hash('sha256', serialize([
                $locationId,
                $serviceId,
                $providerId,
                $rules,
                $exceptions,
            ])),
            locationId: $locationId,
            serviceId: $serviceId,
            providerId: $providerId,
            rules: $rules,
            exceptions: $exceptions,
        ));
    }
}
