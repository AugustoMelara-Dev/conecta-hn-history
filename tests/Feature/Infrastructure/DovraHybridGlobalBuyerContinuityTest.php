<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Application\Availability\AvailabilityQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\HybridGlobalBuyerFixture;
use Tests\TestCase;

final class DovraHybridGlobalBuyerContinuityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_canada_hybrid_fixture_is_idempotent_and_starts_without_conversion_evidence(): void
    {
        $first = HybridGlobalBuyerFixture::seed();
        $firstCounts = HybridGlobalBuyerFixture::conversionCounts((string) $first['tenant_id']);
        $second = HybridGlobalBuyerFixture::seed();
        $secondCounts = HybridGlobalBuyerFixture::conversionCounts((string) $second['tenant_id']);

        self::assertSame($first, $second);
        self::assertSame([
            'orders' => 0,
            'order_items' => 0,
            'appointments' => 0,
            'inventory_movements' => 0,
            'outbox_messages' => 0,
        ], $firstCounts);
        self::assertSame($firstCounts, $secondCounts);
        self::assertSame(1, DB::table('tenants')->where('slug', HybridGlobalBuyerFixture::TENANT_SLUG)->count());
        self::assertSame(1, DB::table('tenant_markets')->where('tenant_id', $first['tenant_id'])->count());
        self::assertSame(1, DB::table('locations')->where('tenant_id', $first['tenant_id'])->where('is_primary', true)->count());
        self::assertSame(1, DB::table('products')->where('tenant_id', $first['tenant_id'])->where('slug', HybridGlobalBuyerFixture::PRODUCT_SLUG)->count());
        self::assertSame(1, DB::table('services')->where('tenant_id', $first['tenant_id'])->where('name', HybridGlobalBuyerFixture::SERVICE_NAME)->count());
        self::assertSame(1, DB::table('service_providers')->where('tenant_id', $first['tenant_id'])->where('name', HybridGlobalBuyerFixture::PROVIDER_NAME)->count());
    }

    public function test_public_order_http_replay_consumes_stock_once_and_preserves_booking_state(): void
    {
        $state = HybridGlobalBuyerFixture::seed();
        $tenantId = (string) $state['tenant_id'];
        $productId = (int) $state['product_id'];
        $availabilityBefore = $this->availabilitySnapshot($tenantId);
        $idempotencyKey = 'hybrid-global-buyer:order:001';
        $payload = [
            'customer_name' => 'Canada Buyer E2E',
            'customer_phone' => '+1 416 555 0100',
            'customer_email' => 'canada.buyer.e2e@example.test',
            'customer_address' => null,
            'customer_notes' => null,
            'delivery_type' => 'pickup',
            'payment_method' => 'pending',
            'items' => [[
                'product_id' => $productId,
                'quantity' => 1,
                'options' => [],
            ]],
        ];

        $first = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', $payload);
        $first->assertCreated()->assertJsonPath('data.status', 'pending');
        $firstJson = $first->json('data');

        $replay = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', $payload);
        $replay->assertOk()->assertJsonPath('data.status', 'pending');
        $replayJson = $replay->json('data');

        self::assertSame($firstJson['order_id'], $replayJson['order_id']);
        self::assertSame($firstJson['order_number'], $replayJson['order_number']);
        self::assertSame('pending', (string) DB::table('orders')->where('id', $firstJson['order_id'])->value('payment_method'));
        self::assertSame('pending', (string) DB::table('orders')->where('id', $firstJson['order_id'])->value('payment_status'));
        self::assertSame(9, (int) DB::table('products')->where('id', $productId)->value('stock_quantity'));
        self::assertSame(1, DB::table('inventory_movements')->where('tenant_id', $tenantId)->count());
        self::assertSame(-1, (int) DB::table('inventory_movements')->where('tenant_id', $tenantId)->value('delta'));
        self::assertSame(1, DB::table('orders')->where('tenant_id', $tenantId)->count());
        self::assertSame(1, DB::table('order_items')->where('order_id', $firstJson['order_id'])->count());
        self::assertSame($availabilityBefore, $this->availabilitySnapshot($tenantId));
        self::assertSame(0, DB::table('appointments')->where('tenant_id', $tenantId)->count());
        self::assertSame(1, $this->businessEventCount($tenantId, 'order:'.$firstJson['order_id'], 'order.created'));
        self::assertSame(0, $this->businessEventCount($tenantId, 'appointment:', 'reservation.created'));

        $conflictingPayload = $payload;
        $conflictingPayload['payment_method'] = 'cash';
        $conflict = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', $conflictingPayload);
        $conflict->assertStatus(422)->assertJsonValidationErrors('idempotency_key');
        self::assertSame(1, DB::table('orders')->where('tenant_id', $tenantId)->count());
        self::assertSame(9, (int) DB::table('products')->where('id', $productId)->value('stock_quantity'));
        self::assertSame(1, DB::table('inventory_movements')->where('tenant_id', $tenantId)->count());
        self::assertSame(1, $this->businessEventCount($tenantId, 'order:'.$firstJson['order_id'], 'order.created'));
        $this->assertNoTenantBillingEvidence($tenantId);
    }

    public function test_fixture_guard_rejects_unsafe_runtime_variants(): void
    {
        $originalEnvironment = app()->environment();
        $originalDatabaseDefault = config('database.default');
        $originalDatabasePath = getenv('DOVRA_E2E_DATABASE_PATH');

        try {
            app()->instance('env', 'production');
            $this->assertFixtureGuardRejects(
                static function (): void {},
                'restricted to local/testing',
            );

            app()->instance('env', $originalEnvironment);
            putenv('DOVRA_E2E_ALLOW_SYNTHETIC_HYBRID_GLOBAL_BUYER');
            $this->assertFixtureGuardRejects(
                static function (): void {},
                'requires an explicit local guard',
            );

            putenv('DOVRA_E2E_ALLOW_SYNTHETIC_HYBRID_GLOBAL_BUYER=true');
            Config::set('database.default', 'pgsql');
            $this->assertFixtureGuardRejects(
                static function (): void {},
                'requires SQLite',
            );

            Config::set('database.default', $originalDatabaseDefault);
            putenv('DOVRA_E2E_DATABASE_PATH='.((string) $originalDatabasePath).'.mismatch');
            $this->assertFixtureGuardRejects(
                static function (): void {},
                'database path mismatch',
            );
        } finally {
            app()->instance('env', $originalEnvironment);
            Config::set('database.default', $originalDatabaseDefault);
            if ($originalDatabasePath === false) {
                putenv('DOVRA_E2E_DATABASE_PATH');
            } else {
                putenv('DOVRA_E2E_DATABASE_PATH='.(string) $originalDatabasePath);
            }
            putenv('DOVRA_E2E_ALLOW_SYNTHETIC_HYBRID_GLOBAL_BUYER=true');
        }
    }

    public function test_public_booking_http_replay_creates_one_pending_appointment_and_only_occupies_slot(): void
    {
        $state = HybridGlobalBuyerFixture::seed();
        $tenantId = (string) $state['tenant_id'];
        $date = HybridGlobalBuyerFixture::nextLocalDate();
        $availabilityBefore = $this->availabilitySnapshot($tenantId);
        $slots = app(AvailabilityQuery::class)->slots(
            $tenantId,
            (int) $state['service_id'],
            (int) $state['provider_id'],
            (string) $state['location_id'],
            CarbonImmutable::parse($date, HybridGlobalBuyerFixture::MARKET_TIMEZONE),
        );
        self::assertNotEmpty($slots);
        $slot = $slots[0];
        $idempotencyKey = 'hybrid-global-buyer:booking:001';
        $payload = [
            'service_id' => (int) $state['service_id'],
            'service_provider_id' => (int) $state['provider_id'],
            'date' => $date,
            'time' => (string) $slot['time'],
            'starts_at_utc' => (string) $slot['starts_at_utc'],
            'location_id' => (string) $state['location_id'],
            'customer_name' => 'Canada Booking E2E',
            'customer_email' => 'canada.booking.e2e@example.test',
            'customer_phone' => '+1 416 555 0101',
            'customer_notes' => 'Synthetic local booking.',
            'idempotency_key' => $idempotencyKey,
        ];
        $url = 'http://'.HybridGlobalBuyerFixture::TENANT_SLUG.'.localhost/book';

        $first = $this->post($url, $payload);
        $first->assertRedirect();
        $firstLocation = (string) $first->headers->get('Location');
        self::assertStringContainsString('/book/success?appointment=', $firstLocation);
        $appointmentId = (int) preg_replace('/^.*appointment=(\d+).*$/', '$1', $firstLocation);
        self::assertGreaterThan(0, $appointmentId);

        $replay = $this->post($url, $payload);
        $replay->assertRedirect();
        self::assertSame($firstLocation, (string) $replay->headers->get('Location'));
        self::assertSame(1, DB::table('appointments')->where('tenant_id', $tenantId)->count());
        self::assertSame('pending', (string) DB::table('appointments')->where('id', $appointmentId)->value('status'));
        self::assertSame('public_booking', (string) DB::table('appointments')->where('id', $appointmentId)->value('source'));
        self::assertSame(HybridGlobalBuyerFixture::MARKET_CURRENCY, (string) DB::table('appointments')->where('id', $appointmentId)->value('currency'));
        self::assertSame(HybridGlobalBuyerFixture::MARKET_TIMEZONE, (string) DB::table('appointments')->where('id', $appointmentId)->value('timezone'));
        self::assertSame(0, DB::table('orders')->where('tenant_id', $tenantId)->count());
        self::assertSame(0, DB::table('inventory_movements')->where('tenant_id', $tenantId)->count());
        self::assertSame($availabilityBefore, $this->availabilitySnapshot($tenantId));
        self::assertSame(1, $this->businessEventCount($tenantId, 'appointment:'.$appointmentId, 'reservation.created'));
        $afterSlots = app(AvailabilityQuery::class)->slots(
            $tenantId,
            (int) $state['service_id'],
            (int) $state['provider_id'],
            (string) $state['location_id'],
            CarbonImmutable::parse($date, HybridGlobalBuyerFixture::MARKET_TIMEZONE),
        );
        self::assertFalse(collect($afterSlots)->contains('starts_at_utc', $slot['starts_at_utc']));
        $this->assertNoTenantBillingEvidence($tenantId);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function availabilitySnapshot(string $tenantId): array
    {
        return [
            'rules' => DB::table('availability_rules')->where('tenant_id', $tenantId)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all(),
            'exceptions' => DB::table('availability_exceptions')->where('tenant_id', $tenantId)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all(),
        ];
    }

    private function businessEventCount(string $tenantId, string $aggregatePrefix, string $eventName): int
    {
        $count = 0;
        foreach (DB::table('outbox_messages')->get() as $message) {
            $payload = is_array($message->payload)
                ? $message->payload
                : json_decode((string) $message->payload, true, 512, JSON_THROW_ON_ERROR);
            $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $aggregateId = (string) ($meta['aggregate_id'] ?? $data['aggregateId'] ?? $data['aggregate_id'] ?? '');
            $payloadTenant = (string) ($meta['tenant_id'] ?? $data['tenantId'] ?? $data['tenant_id'] ?? '');
            $actualEvent = (string) ($data['eventName'] ?? $data['event_name'] ?? '');
            if ($payloadTenant === $tenantId && str_starts_with($aggregateId, $aggregatePrefix) && $actualEvent === $eventName) {
                $count++;
            }
        }

        return $count;
    }

    private function assertNoTenantBillingEvidence(string $tenantId): void
    {
        foreach ([
            'payments',
            'subscription_payments',
            'subscription_intents',
            'tenant_billing_references',
            'global_billing_entitlements',
            'global_billing_credit_accounts',
            'global_billing_credit_reservations',
            'global_billing_credit_ledger_entries',
            'global_billing_credit_alerts',
            'global_billing_credit_reconciliations',
            'global_billing_lifecycle_events',
            'ai_credit_reservations',
            'ai_credit_ledger_entries',
            'ai_credit_usage_alerts',
            'ai_credit_grants',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                self::fail("{$table} canonical negative evidence table is missing.");
            }
            if (! Schema::hasColumn($table, 'tenant_id')) {
                self::fail("{$table} canonical negative evidence table lacks tenant_id.");
            }
            self::assertSame(0, DB::table($table)->where('tenant_id', $tenantId)->count(), $table);
        }
    }

    private function assertFixtureGuardRejects(callable $setup, string $message): void
    {
        $setup();

        try {
            HybridGlobalBuyerFixture::seed();
            self::fail('The synthetic fixture guard unexpectedly accepted an unsafe runtime variant.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }
}
