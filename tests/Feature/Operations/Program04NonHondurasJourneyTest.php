<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Availability\AvailabilityRuleWriter;
use App\Application\Availability\Commands\ReplaceAvailabilityRulesCommand;
use App\Application\Imports\OperationalImportService;
use App\Application\Orders\CanonicalOrderWriter;
use App\Application\Orders\Commands\CreateOrderCommand;
use App\Application\Quotes\CanonicalQuoteWriter;
use App\Application\Quotes\Commands\CreateQuoteCommand;
use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\CreateReservationCommand;
use App\Models\Appointment;
use App\Models\CatalogImportItem;
use App\Models\CrmDeal;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class Program04NonHondurasJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_canadian_hybrid_tenant_operates_every_canonical_aggregate_end_to_end(): void
    {
        Queue::fake();
        $owner = User::factory()->tenantOwner()->create(['email' => 'program04-ca@example.test']);
        $tenant = Tenant::factory()->forUser($owner)->pro()->create([
            'business_type' => 'service',
            'commercial_mode' => 'hybrid',
            'delivery_enabled' => true,
            'pickup_enabled' => true,
        ]);
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $location = GlobalMarketFixture::assignCanada($tenant);

        $productId = $this->publishImport(
            $tenant,
            $owner,
            'product',
            'products.csv',
            "name,price_minor,currency,stock_quantity,published\nTravel mug,2500,CAD,5,true",
        );
        $serviceId = $this->publishImport(
            $tenant,
            $owner,
            'service',
            'services.csv',
            "name,description,price_minor,currency,duration_minutes,location_id,timezone,published\n"
                ."Consultation,Strategy session,10000,CAD,60,{$location->id},America/Toronto,true",
        );
        $dealId = $this->publishImport(
            $tenant,
            $owner,
            'opportunity',
            'opportunities.csv',
            "title,value_minor,currency,location_id,source\nToronto rollout,50000,CAD,{$location->id},journey",
        );

        $product = Product::query()->withoutGlobalScopes()->findOrFail((int) $productId);
        $service = Service::query()->withoutGlobalScopes()->findOrFail((int) $serviceId);
        $deal = CrmDeal::query()->withoutGlobalScopes()->findOrFail((int) $dealId);
        $provider = ServiceProvider::factory()->create(['tenant_id' => $tenant->id]);
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
            idempotencyKey: 'program04:availability:ca',
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

        $order = app(CanonicalOrderWriter::class)->create(new CreateOrderCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'program04:order:ca',
            customerName: 'Canadian buyer',
            customerPhone: '+1 416 555 0100',
            customerEmail: 'buyer@example.test',
            customerAddress: null,
            customerNotes: null,
            deliveryType: 'pickup',
            paymentMethod: 'cash',
            items: [[
                'product_id' => (int) $product->id,
                'quantity' => 2,
                'options' => [],
            ]],
            userId: (string) $owner->id,
            source: 'authenticated',
            requestedCurrency: 'CAD',
        ))->order;
        $reservation = app(CanonicalReservationWriter::class)->create(new CreateReservationCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'program04:reservation:ca',
            serviceId: (int) $service->id,
            providerId: (int) $provider->id,
            locationId: (string) $location->id,
            startsAtUtc: CarbonImmutable::parse('2026-08-03 13:00:00', 'UTC'),
            customerName: 'Canadian client',
            customerEmail: 'client@example.test',
            customerPhone: '+1 416 555 0101',
            customerNotes: null,
            source: 'authenticated',
        ))->appointment;
        $quote = app(CanonicalQuoteWriter::class)->create(new CreateQuoteCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'program04:quote:ca',
            locationId: (string) $location->id,
            items: [
                ['product_id' => $product->id, 'quantity' => 1],
                ['service_id' => $service->id, 'quantity' => 1],
            ],
            expiresAt: CarbonImmutable::parse('2026-08-10 13:00:00', 'UTC'),
        ))->quote;

        self::assertSame('CAD', $product->currency);
        self::assertSame(2_500, (int) $product->price_minor);
        self::assertSame('CAD', $service->currency);
        self::assertSame('CAD', $deal->currency);
        self::assertSame('CAD', $order->currency);
        self::assertSame('CAD', $reservation->currency);
        self::assertSame('America/Toronto', $reservation->timezone);
        self::assertSame('2026-08-03', $reservation->local_date->format('Y-m-d'));
        self::assertSame('CAD', $quote->currency);
        self::assertSame(12_500, (int) $quote->total_minor);
        self::assertSame(3, (int) $product->fresh()->stock_quantity);

        $other = Tenant::factory()->create();
        foreach ([
            [Product::class, $product->id],
            [Service::class, $service->id],
            [CrmDeal::class, $deal->id],
            [Order::class, $order->id],
            [Appointment::class, $reservation->id],
            [Quote::class, $quote->id],
        ] as [$model, $id]) {
            self::assertFalse($model::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $other->id)
                ->whereKey($id)
                ->exists());
        }
    }

    private function publishImport(
        Tenant $tenant,
        User $owner,
        string $kind,
        string $name,
        string $csv,
    ): string {
        $service = app(OperationalImportService::class);
        $batchId = $service->stage(
            (string) $tenant->id,
            (string) $owner->id,
            UploadedFile::fake()->createWithContent($name, $csv),
            $kind,
            "program04:import:{$kind}",
        );
        $service->process((string) $tenant->id, $batchId);
        $item = CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('batch_id', $batchId)
            ->firstOrFail();
        $service->review(
            (string) $tenant->id,
            (string) $owner->id,
            $batchId,
            [(string) $item->id => 'approve'],
        );
        $service->publish((string) $tenant->id, (string) $owner->id, $batchId);

        return (string) $item->fresh()->aggregate_id;
    }
}
