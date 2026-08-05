<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Availability\AvailabilityRuleWriter;
use App\Application\Availability\Commands\ReplaceAvailabilityRulesCommand;
use App\Application\Quotes\CanonicalQuoteWriter;
use App\Application\Quotes\Commands\CreateQuoteCommand;
use App\Application\Quotes\Commands\ReviseQuoteCommand;
use App\Application\Quotes\Commands\TransitionQuoteCommand;
use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\CreateReservationCommand;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Models\Location;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class CanonicalQuoteWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_uses_server_prices_and_replays_with_immutable_snapshots(): void
    {
        [$tenant, $owner, $location, $product, $service] = $this->catalog();
        $writer = app(CanonicalQuoteWriter::class);
        $command = new CreateQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'quote:create:1',
            locationId: (string) $location->id,
            items: [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'discount_minor' => 100,
                    'tax_minor' => 50,
                ],
                [
                    'service_id' => $service->id,
                    'quantity' => 1,
                ],
            ],
            expiresAt: CarbonImmutable::parse('2026-08-10 13:00:00', 'UTC'),
        );

        $first = $writer->create($command);
        $second = $writer->create($command);

        self::assertFalse($first->replayed);
        self::assertTrue($second->replayed);
        self::assertSame($first->quote->id, $second->quote->id);
        self::assertTrue(Str::isUuid($first->quote->id));
        self::assertTrue(Str::isUuid($first->quote->public_id));
        self::assertNotSame($first->quote->id, $first->quote->public_id);
        self::assertSame(15_000, (int) $first->quote->subtotal_minor);
        self::assertSame(100, (int) $first->quote->discount_minor);
        self::assertSame(50, (int) $first->quote->tax_minor);
        self::assertSame(14_950, (int) $first->quote->total_minor);
        self::assertSame('CAD', $first->quote->currency);
        self::assertSame(Quote::STATUS_DRAFT, $first->quote->status);
        self::assertCount(2, $first->quote->items);
        self::assertSame('Travel mug', $first->quote->items[0]->description);
        self::assertSame(2_500, (int) $first->quote->items[0]->unit_price_minor);
        self::assertSame('Consultation', $first->quote->items[1]->description);
        self::assertSame(10_000, (int) $first->quote->items[1]->unit_price_minor);
        self::assertSame(1, DB::table('outbox_messages')
            ->where('type', BusinessOperationChanged::class)
            ->where('aggregate_id', 'quote:'.$first->quote->id)
            ->count());

        $product->forceFill(['price_minor' => 9_999, 'price' => 99.99])->save();
        self::assertSame(2_500, (int) $first->quote->items[0]->fresh()->unit_price_minor);

        try {
            $writer->create(new CreateQuoteCommand(
                tenantId: (string) $tenant->id,
                actorId: (string) $owner->id,
                idempotencyKey: 'quote:create:1',
                locationId: (string) $location->id,
                items: [['product_id' => $product->id, 'quantity' => 1]],
            ));
            self::fail('Quote idempotency drift must fail.');
        } catch (ValidationException|\LogicException) {
            self::assertSame(1, DB::table('quotes')->where('tenant_id', $tenant->id)->count());
        }
    }

    public function test_issue_revision_and_expiry_are_tenant_safe_and_never_mutate_issued_versions(): void
    {
        [$tenant, $owner, $location, $product] = $this->catalog();
        [$other, $otherOwner, $otherLocation, $otherProduct] = $this->catalog('quote-other@example.test');
        $writer = app(CanonicalQuoteWriter::class);

        foreach ([
            [$otherLocation->id, $product->id],
            [$location->id, $otherProduct->id],
        ] as [$locationId, $productId]) {
            try {
                $writer->create(new CreateQuoteCommand(
                    tenantId: (string) $tenant->id,
                    actorId: (string) $owner->id,
                    idempotencyKey: 'quote:foreign:'.$locationId.':'.$productId,
                    locationId: (string) $locationId,
                    items: [['product_id' => $productId, 'quantity' => 1]],
                ));
                self::fail('Cross-tenant quote references must be hidden.');
            } catch (ModelNotFoundException) {
                self::assertDatabaseCount('quotes', 0);
            }
        }

        $draft = $writer->create(new CreateQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'quote:lifecycle:create',
            locationId: (string) $location->id,
            items: [['product_id' => $product->id, 'quantity' => 1]],
            expiresAt: CarbonImmutable::parse('2026-08-10 13:00:00', 'UTC'),
        ))->quote;
        $issued = $writer->issue(new TransitionQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            quoteId: (string) $draft->id,
            idempotencyKey: 'quote:lifecycle:issue',
            targetStatus: Quote::STATUS_ISSUED,
        ))->quote;
        self::assertSame(Quote::STATUS_ISSUED, $issued->status);
        self::assertNotNull($issued->issued_at);

        $revised = $writer->revise(new ReviseQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            quoteId: (string) $issued->id,
            idempotencyKey: 'quote:lifecycle:revise',
            items: [['product_id' => $product->id, 'quantity' => 3]],
        ))->quote;
        self::assertNotSame($issued->id, $revised->id);
        self::assertSame($issued->id, $revised->supersedes_quote_id);
        self::assertSame(2, (int) $revised->version);
        self::assertSame(Quote::STATUS_DRAFT, $revised->status);
        self::assertSame(2_500, (int) $issued->fresh()->total_minor);
        self::assertSame(7_500, (int) $revised->total_minor);

        CarbonImmutable::setTestNow('2026-08-11 13:00:00 UTC');
        $expired = $writer->expire(new TransitionQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            quoteId: (string) $issued->id,
            idempotencyKey: 'quote:lifecycle:expire',
            targetStatus: Quote::STATUS_EXPIRED,
        ))->quote;
        self::assertSame(Quote::STATUS_EXPIRED, $expired->status);
        CarbonImmutable::setTestNow();
        self::assertNotSame($tenant->id, $other->id);
        self::assertNotSame($owner->id, $otherOwner->id);
    }

    public function test_acceptance_returns_a_new_draft_on_price_drift_and_order_conversion_is_exactly_once(): void
    {
        [$tenant, $owner, $location, $product] = $this->catalog();
        $writer = app(CanonicalQuoteWriter::class);
        $quote = $writer->create(new CreateQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'quote:conversion:create',
            locationId: (string) $location->id,
            items: [['product_id' => $product->id, 'quantity' => 1]],
        ))->quote;
        $writer->issue(new TransitionQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            quoteId: (string) $quote->id,
            idempotencyKey: 'quote:conversion:issue',
            targetStatus: Quote::STATUS_ISSUED,
        ));

        $product->forceFill(['price' => 30, 'price_minor' => 3_000])->save();
        $changed = $writer->accept(new TransitionQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            quoteId: (string) $quote->id,
            idempotencyKey: 'quote:conversion:accept-drift',
            targetStatus: Quote::STATUS_ACCEPTED,
        ));

        self::assertSame('quote_changed', $changed->outcome);
        self::assertSame(Quote::STATUS_ISSUED, $quote->fresh()->status);
        self::assertNotNull($changed->revisedQuote);
        self::assertSame(Quote::STATUS_DRAFT, $changed->revisedQuote->status);
        self::assertSame($quote->id, $changed->revisedQuote->supersedes_quote_id);
        self::assertSame(3_000, (int) $changed->revisedQuote->total_minor);

        $revised = $changed->revisedQuote;
        $writer->issue(new TransitionQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            quoteId: (string) $revised->id,
            idempotencyKey: 'quote:conversion:revised-issue',
            targetStatus: Quote::STATUS_ISSUED,
        ));
        $accepted = $writer->accept(new TransitionQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            quoteId: (string) $revised->id,
            idempotencyKey: 'quote:conversion:revised-accept',
            targetStatus: Quote::STATUS_ACCEPTED,
        ));
        self::assertSame(Quote::STATUS_ACCEPTED, $accepted->quote->status);
        self::assertNotNull($accepted->quote->accepted_at);

        $conversion = new TransitionQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            quoteId: (string) $revised->id,
            idempotencyKey: 'quote:conversion:order',
            targetStatus: Quote::ACTION_CONVERT_TO_ORDER,
            metadata: [
                'customer_name' => 'Quote customer',
                'customer_phone' => '+1 416 555 0199',
                'customer_email' => 'quote.customer@example.test',
                'delivery_type' => 'pickup',
                'payment_method' => 'cash',
            ],
        );
        $converted = $writer->convertToOrder($conversion);
        $replayed = $writer->convertToOrder($conversion);

        self::assertNotNull($converted->quote->converted_order_id);
        self::assertSame($converted->quote->converted_order_id, $replayed->quote->converted_order_id);
        self::assertTrue($replayed->replayed);
        self::assertSame(1, DB::table('orders')->where('tenant_id', $tenant->id)->count());
        self::assertSame(30.0, (float) DB::table('orders')
            ->where('id', $converted->quote->converted_order_id)
            ->value('total'));
        self::assertSame(9, (int) $product->fresh()->stock_quantity);
    }

    public function test_service_acceptance_detects_availability_drift_and_reservation_conversion_is_exactly_once(): void
    {
        [$tenant, $owner, $location, , $service] = $this->catalog();
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
            idempotencyKey: 'quote:availability:1',
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
        $writer = app(CanonicalQuoteWriter::class);
        $occupiedQuote = $writer->create(new CreateQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'quote:reservation:occupied',
            locationId: (string) $location->id,
            items: [[
                'service_id' => $service->id,
                'quantity' => 1,
                'metadata' => [
                    'provider_id' => $provider->id,
                    'starts_at_utc' => '2026-08-03T13:00:00+00:00',
                ],
            ]],
        ))->quote;
        $writer->issue(new TransitionQuoteCommand(
            (string) $tenant->id,
            (string) $owner->id,
            (string) $occupiedQuote->id,
            'quote:reservation:occupied:issue',
            Quote::STATUS_ISSUED,
        ));
        app(CanonicalReservationWriter::class)->create(new CreateReservationCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'quote:reservation:competitor',
            serviceId: (int) $service->id,
            providerId: (int) $provider->id,
            locationId: (string) $location->id,
            startsAtUtc: CarbonImmutable::parse('2026-08-03 13:00:00', 'UTC'),
            customerName: 'Competing customer',
            customerEmail: 'competing@example.test',
            customerPhone: '+1 416 555 0120',
            customerNotes: null,
            source: 'authenticated',
        ));
        $drift = $writer->accept(new TransitionQuoteCommand(
            (string) $tenant->id,
            (string) $owner->id,
            (string) $occupiedQuote->id,
            'quote:reservation:occupied:accept',
            Quote::STATUS_ACCEPTED,
        ));
        self::assertSame('quote_changed', $drift->outcome);
        self::assertSame(Quote::STATUS_ISSUED, $occupiedQuote->fresh()->status);

        $convertible = $writer->create(new CreateQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'quote:reservation:convertible',
            locationId: (string) $location->id,
            items: [[
                'service_id' => $service->id,
                'quantity' => 1,
                'metadata' => [
                    'provider_id' => $provider->id,
                    'starts_at_utc' => '2026-08-03T15:00:00+00:00',
                ],
            ]],
        ))->quote;
        $writer->issue(new TransitionQuoteCommand(
            (string) $tenant->id,
            (string) $owner->id,
            (string) $convertible->id,
            'quote:reservation:convertible:issue',
            Quote::STATUS_ISSUED,
        ));
        $writer->accept(new TransitionQuoteCommand(
            (string) $tenant->id,
            (string) $owner->id,
            (string) $convertible->id,
            'quote:reservation:convertible:accept',
            Quote::STATUS_ACCEPTED,
        ));
        $conversion = new TransitionQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            quoteId: (string) $convertible->id,
            idempotencyKey: 'quote:reservation:convert',
            targetStatus: Quote::ACTION_CONVERT_TO_RESERVATION,
            metadata: [
                'customer_name' => 'Reservation quote customer',
                'customer_email' => 'reservation.quote@example.test',
                'customer_phone' => '+1 416 555 0130',
            ],
        );
        $converted = $writer->convertToReservation($conversion);
        $replayed = $writer->convertToReservation($conversion);

        self::assertNotNull($converted->quote->converted_appointment_id);
        self::assertSame(
            $converted->quote->converted_appointment_id,
            $replayed->quote->converted_appointment_id,
        );
        self::assertTrue($replayed->replayed);
        self::assertSame(2, DB::table('appointments')->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('appointments', [
            'id' => $converted->quote->converted_appointment_id,
            'service_id' => $service->id,
            'service_provider_id' => $provider->id,
            'location_id' => $location->id,
            'source' => 'quote_conversion',
            'currency' => 'CAD',
            'price_minor' => 10_000,
        ]);
    }

    /**
     * @return array{Tenant, User, Location, Product, Service}
     */
    private function catalog(string $email = 'quote-owner@example.test'): array
    {
        $owner = User::factory()->create(['email' => $email]);
        $tenant = Tenant::factory()->forUser($owner)->pro()->create([
            'business_type' => 'service',
            'commercial_mode' => 'hybrid',
        ]);
        $location = GlobalMarketFixture::assignCanada($tenant);
        $product = Product::factory()->forTenant($tenant)->create([
            'name' => 'Travel mug',
            'price' => 25,
            'price_minor' => 2_500,
            'currency' => 'CAD',
        ]);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Consultation',
            'price' => 100,
            'price_minor' => 10_000,
            'currency' => 'CAD',
            'duration_minutes' => 60,
        ]);

        return [$tenant, $owner, $location, $product, $service];
    }
}
