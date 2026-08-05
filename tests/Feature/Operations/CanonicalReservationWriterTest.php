<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Availability\AvailabilityRuleWriter;
use App\Application\Availability\Commands\ReplaceAvailabilityRulesCommand;
use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\CreateReservationCommand;
use App\Application\Reservations\Commands\TransitionReservationCommand;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Booking\GuestAppointmentManagementReader;
use App\Infrastructure\Booking\HttpPublicBookingResponder;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class CanonicalReservationWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_snapshots_market_service_time_and_replays_idempotently(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();
        $writer = app(CanonicalReservationWriter::class);
        $command = $this->command($tenant, $owner, $location, $service, $provider, 'reservation:create:1');

        $first = $writer->create($command);
        $second = $writer->create($command);

        self::assertFalse($first->replayed);
        self::assertTrue($second->replayed);
        self::assertSame($first->appointment->id, $second->appointment->id);
        self::assertSame('Consultation', $first->appointment->service_name_snapshot);
        self::assertSame(60, (int) $first->appointment->duration_minutes);
        self::assertSame(10_000, (int) $first->appointment->price_minor);
        self::assertSame('CAD', $first->appointment->currency);
        self::assertSame('America/Toronto', $first->appointment->timezone);
        self::assertSame('2026-08-03', $first->appointment->local_date->format('Y-m-d'));
        self::assertSame('2026-08-03 13:00:00', $first->appointment->getRawOriginal('scheduled_at'));
        self::assertSame('2026-08-03 14:00:00', $first->appointment->getRawOriginal('end_at'));
        self::assertSame(1, DB::table('appointments')->where('tenant_id', $tenant->id)->count());
        self::assertSame(1, DB::table('outbox_messages')
            ->where('type', BusinessOperationChanged::class)
            ->where('aggregate_id', 'appointment:'.$first->appointment->id)
            ->count());

        try {
            $writer->create($this->command(
                $tenant,
                $owner,
                $location,
                $service,
                $provider,
                'reservation:create:1',
                CarbonImmutable::parse('2026-08-03 14:00:00', 'UTC'),
            ));
            self::fail('Changed reservation payload cannot reuse an idempotency key.');
        } catch (ValidationException|\LogicException) {
            self::assertSame(1, DB::table('appointments')->where('tenant_id', $tenant->id)->count());
        }
    }

    public function test_overlap_cross_tenant_references_and_cancelled_slot_rules_fail_closed(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();
        [$other, $otherOwner, $otherLocation, $otherService, $otherProvider] = $this->catalog(
            'reservation-other@example.test',
        );
        $writer = app(CanonicalReservationWriter::class);
        $first = $writer->create($this->command(
            $tenant,
            $owner,
            $location,
            $service,
            $provider,
            'reservation:overlap:first',
        ));

        try {
            $writer->create($this->command(
                $tenant,
                $owner,
                $location,
                $service,
                $provider,
                'reservation:overlap:second',
            ));
            self::fail('An active provider interval cannot be double booked.');
        } catch (ValidationException) {
            self::assertSame(1, DB::table('appointments')->where('tenant_id', $tenant->id)->count());
        }

        $writer->cancel(new TransitionReservationCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            appointmentId: (int) $first->appointment->id,
            targetStatus: Appointment::STATUS_CANCELLED,
            reason: 'Customer rescheduled',
        ));
        $replacement = $writer->create($this->command(
            $tenant,
            $owner,
            $location,
            $service,
            $provider,
            'reservation:overlap:replacement',
        ));
        self::assertSame(Appointment::STATUS_PENDING, $replacement->appointment->status);

        foreach ([
            [$otherLocation, $service, $provider],
            [$location, $otherService, $provider],
            [$location, $service, $otherProvider],
        ] as [$attemptLocation, $attemptService, $attemptProvider]) {
            try {
                $writer->create($this->command(
                    $tenant,
                    $owner,
                    $attemptLocation,
                    $attemptService,
                    $attemptProvider,
                    'reservation:foreign:'.(string) $attemptLocation->id.':'.$attemptService->id.':'.$attemptProvider->id,
                    CarbonImmutable::parse('2026-08-03 15:00:00', 'UTC'),
                ));
                self::fail('Cross-tenant reservation references must be hidden.');
            } catch (ModelNotFoundException) {
                self::assertSame(2, DB::table('appointments')->where('tenant_id', $tenant->id)->count());
            }
        }
        try {
            $writer->create($this->command(
                $tenant,
                $owner,
                $location,
                $service,
                $provider,
                'reservation:foreign-customer',
                CarbonImmutable::parse('2026-08-03 15:00:00', 'UTC'),
                $otherOwner,
            ));
            self::fail('A customer identity from another tenant must be hidden.');
        } catch (ModelNotFoundException) {
            self::assertSame(2, DB::table('appointments')->where('tenant_id', $tenant->id)->count());
        }
        self::assertNotSame($tenant->id, $other->id);
        self::assertNotSame($owner->id, $otherOwner->id);
    }

    public function test_lifecycle_is_tenant_safe_and_terminal_states_are_atomic(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();
        [$other, $otherOwner] = $this->catalog('reservation-state-other@example.test');
        $writer = app(CanonicalReservationWriter::class);
        $appointment = $writer->create($this->command(
            $tenant,
            $owner,
            $location,
            $service,
            $provider,
            'reservation:state:1',
        ))->appointment;

        try {
            $writer->transition(new TransitionReservationCommand(
                (string) $other->id,
                (string) $otherOwner->id,
                (int) $appointment->id,
                Appointment::STATUS_CONFIRMED,
            ));
            self::fail('Foreign tenant transitions must hide the appointment.');
        } catch (ModelNotFoundException) {
            self::assertSame(Appointment::STATUS_PENDING, $appointment->fresh()->status);
        }

        $confirmed = $writer->transition(new TransitionReservationCommand(
            (string) $tenant->id,
            (string) $owner->id,
            (int) $appointment->id,
            Appointment::STATUS_CONFIRMED,
        ));
        self::assertSame(Appointment::STATUS_CONFIRMED, $confirmed->appointment->status);
        self::assertNotNull($confirmed->appointment->confirmed_at);
        $noShow = $writer->transition(new TransitionReservationCommand(
            (string) $tenant->id,
            (string) $owner->id,
            (int) $appointment->id,
            Appointment::STATUS_NO_SHOW,
            reason: 'Customer did not arrive',
        ));
        self::assertSame(Appointment::STATUS_NO_SHOW, $noShow->appointment->status);
        self::assertNotNull($noShow->appointment->no_show_at);
        $events = DB::table('outbox_messages')->count();

        try {
            $writer->transition(new TransitionReservationCommand(
                (string) $tenant->id,
                (string) $owner->id,
                (int) $appointment->id,
                Appointment::STATUS_COMPLETED,
            ));
            self::fail('No-show is terminal.');
        } catch (ValidationException|\LogicException) {
            self::assertSame(Appointment::STATUS_NO_SHOW, $appointment->fresh()->status);
            self::assertSame($events, DB::table('outbox_messages')->count());
        }
    }

    public function test_public_booking_and_signed_guest_management_adapters_use_the_canonical_owner(): void
    {
        [$tenant, $owner, $location, $service, $provider] = $this->catalog();
        app(TenantContext::class)->setTenant((string) $tenant->id);
        app()->instance('current_tenant', $tenant);
        $payload = [
            'service_id' => $service->id,
            'service_provider_id' => $provider->id,
            'location_id' => $location->id,
            'date' => '2026-08-03',
            'time' => '09:00',
            'starts_at_utc' => '2026-08-03T13:00:00+00:00',
            'customer_name' => 'Public customer',
            'customer_email' => 'public.customer@example.test',
            'customer_phone' => '+1 416 555 0110',
            'idempotency_key' => 'public-adapter:reservation:1',
        ];
        $responder = app(HttpPublicBookingResponder::class);

        $first = $responder->store(Request::create('/book', 'POST', $payload));
        if (! app(TenantContext::class)->isInitialized()) {
            app(TenantContext::class)->setTenant((string) $tenant->id);
        }
        app()->instance('current_tenant', $tenant);
        $second = $responder->store(Request::create('/book', 'POST', $payload));

        self::assertInstanceOf(RedirectResponse::class, $first);
        self::assertInstanceOf(RedirectResponse::class, $second);
        self::assertSame(1, Appointment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('source', 'public_booking')
            ->count());
        $appointment = Appointment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        $guest = app(GuestAppointmentManagementReader::class);
        self::assertTrue($guest->cancel($appointment->id));
        self::assertFalse($guest->cancel($appointment->id));
        self::assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
        self::assertSame('Guest cancellation from signed management link', $appointment->fresh()->status_reason);
        self::assertNotNull($appointment->fresh()->cancelled_at);
        self::assertSame(1, DB::table('outbox_messages')
            ->where('aggregate_id', 'appointment:'.$appointment->id)
            ->where('payload', 'like', '%reservation.cancelled%')
            ->count());
        app(TenantContext::class)->reset();
        app()->forgetInstance('current_tenant');
        self::assertNotNull($owner->id);
    }

    /**
     * @return array{Tenant, User, Location, Service, ServiceProvider}
     */
    private function catalog(string $email = 'reservation-owner@example.test'): array
    {
        $owner = User::factory()->create(['email' => $email]);
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'service',
            'commercial_mode' => 'appointments',
        ]);
        $location = GlobalMarketFixture::assignCanada($tenant);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Consultation',
            'duration_minutes' => 60,
            'price' => 100,
            'price_minor' => 10_000,
            'currency' => 'CAD',
        ]);
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
        app(AvailabilityRuleWriter::class)->replace(new ReplaceAvailabilityRulesCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'reservation:availability:'.$tenant->id,
            locationId: (string) $location->id,
            serviceId: (int) $service->id,
            providerId: (int) $provider->id,
            rules: [[
                'weekday' => 1,
                'starts_at_local' => '09:00',
                'ends_at_local' => '17:00',
            ]],
            exceptions: [],
        ));

        return [$tenant, $owner, $location, $service, $provider];
    }

    private function command(
        Tenant $tenant,
        User $owner,
        Location $location,
        Service $service,
        ServiceProvider $provider,
        string $idempotencyKey,
        ?CarbonImmutable $startsAtUtc = null,
        ?User $customer = null,
    ): CreateReservationCommand {
        return new CreateReservationCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: $idempotencyKey,
            serviceId: (int) $service->id,
            providerId: (int) $provider->id,
            locationId: (string) $location->id,
            startsAtUtc: $startsAtUtc ?? CarbonImmutable::parse('2026-08-03 13:00:00', 'UTC'),
            customerName: 'Reservation customer',
            customerEmail: 'reservation.customer@example.test',
            customerPhone: '+1 416 555 0101',
            customerNotes: null,
            userId: $customer?->id,
            source: 'authenticated',
        );
    }
}
