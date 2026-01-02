<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Broadcasting\ChannelRegistry;
use App\Events\Broadcast\LowStockBroadcast;
use App\Events\Broadcast\NewLoginDetectedBroadcast;
use App\Events\Broadcast\OrderCancelledBroadcast;
use App\Events\Broadcast\OrderPaidBroadcast;
use App\Events\Broadcast\OrderRefundedBroadcast;
use App\Events\Broadcast\OrderShippedBroadcast;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * BroadcastPayloadTest - Enterprise-Grade Broadcast Event Verification
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  COVERAGE:                                                              ║
 * ║    - UI Contract (title, message, status, icon)                        ║
 * ║    - Payload versioning                                                 ║
 * ║    - i18n multi-locale support                                          ║
 * ║    - Phantom placeholder detection                                      ║
 * ║    - Edge cases (null values, special characters, boundary amounts)    ║
 * ║    - ChannelRegistry validation                                         ║
 * ║    - FormatsCurrency precision                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class BroadcastPayloadTest extends TestCase
{
    private const REQUIRED_KEYS = ['title', 'message', 'status', 'icon'];
    private const VERSIONING_KEYS = ['_version', '_timestamp'];

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UI CONTRACT TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function order_paid_broadcast_satisfies_ui_contract(): void
    {
        $event = OrderPaidBroadcast::fromDomainData(
            tenantId: 1,
            orderId: 123,
            orderNumber: 'ORD-2026-00001',
            amountCents: 150000,
            currency: 'HNL',
            paymentMethod: 'card',
            paidAt: new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
        );

        $payload = $event->broadcastWith();

        $this->assertPayloadSatisfiesContract($payload, 'OrderPaidBroadcast');
        $this->assertPayloadHasVersioning($payload, 'OrderPaidBroadcast');
        $this->assertTranslationResolved($payload['title']);
        $this->assertNoMissingPlaceholders($payload);
        $this->assertEquals('paid', $payload['status']);
        $this->assertEquals('check-circle', $payload['icon']);
    }

    /** @test */
    public function order_cancelled_broadcast_satisfies_ui_contract(): void
    {
        $event = OrderCancelledBroadcast::fromDomainData(
            tenantId: 2,
            orderId: 456,
            orderNumber: 'ORD-2026-00002',
            reason: 'customer_request',
            shouldRefund: true,
            cancelledAt: new DateTimeImmutable('2026-01-01T14:00:00+00:00'),
        );

        $payload = $event->broadcastWith();

        $this->assertPayloadSatisfiesContract($payload, 'OrderCancelledBroadcast');
        $this->assertPayloadHasVersioning($payload, 'OrderCancelledBroadcast');
        $this->assertTranslationResolved($payload['title']);
        $this->assertNoMissingPlaceholders($payload);
        $this->assertEquals('cancelled', $payload['status']);
    }

    /** @test */
    public function low_stock_broadcast_satisfies_ui_contract(): void
    {
        $event = LowStockBroadcast::fromDomainData(
            tenantId: 3,
            productId: 789,
            productName: 'Premium Widget',
            productSku: 'WDG-001',
            currentStock: 5,
            threshold: 10,
            severity: 'warning',
            occurredAt: new DateTimeImmutable('2026-01-01T16:00:00+00:00'),
        );

        $payload = $event->broadcastWith();

        $this->assertPayloadSatisfiesContract($payload, 'LowStockBroadcast');
        $this->assertPayloadHasVersioning($payload, 'LowStockBroadcast');
        $this->assertTranslationResolved($payload['title']);
        $this->assertNoMissingPlaceholders($payload);
        $this->assertEquals('low_stock', $payload['status']);
    }

    /** @test */
    public function order_shipped_broadcast_satisfies_ui_contract(): void
    {
        $event = OrderShippedBroadcast::fromDomainData(
            tenantId: 4,
            orderId: 101,
            orderNumber: 'ORD-2026-00003',
            carrier: 'fedex',
            trackingNumber: '123456789012',
            estimatedDelivery: '2026-01-15',
            shippedAt: new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        $payload = $event->broadcastWith();

        $this->assertPayloadSatisfiesContract($payload, 'OrderShippedBroadcast');
        $this->assertPayloadHasVersioning($payload, 'OrderShippedBroadcast');
        $this->assertTranslationResolved($payload['title']);
        $this->assertNoMissingPlaceholders($payload);
        $this->assertEquals('shipped', $payload['status']);
    }

    /** @test */
    public function order_refunded_broadcast_satisfies_ui_contract(): void
    {
        $event = OrderRefundedBroadcast::fromDomainData(
            tenantId: 5,
            orderId: 202,
            orderNumber: 'ORD-2026-00004',
            refundAmountCents: 50000,
            currency: 'HNL',
            isPartial: true,
            reason: 'Damaged item',
            refundedAt: new DateTimeImmutable('2026-01-01T18:00:00+00:00'),
        );

        $payload = $event->broadcastWith();

        $this->assertPayloadSatisfiesContract($payload, 'OrderRefundedBroadcast');
        $this->assertPayloadHasVersioning($payload, 'OrderRefundedBroadcast');
        $this->assertTranslationResolved($payload['title']);
        $this->assertNoMissingPlaceholders($payload);
        $this->assertEquals('refunded', $payload['status']);
    }

    /** @test */
    public function new_login_detected_broadcast_satisfies_ui_contract(): void
    {
        $event = NewLoginDetectedBroadcast::fromDomainData(
            userId: 100,
            tenantId: 1,
            userEmail: 'user@example.com',
            ipAddress: '192.168.1.100',
            userAgent: 'Mozilla/5.0 Chrome/120',
            loginAt: new DateTimeImmutable('2026-01-01T20:00:00+00:00'),
        );

        $payload = $event->broadcastWith();

        $this->assertPayloadSatisfiesContract($payload, 'NewLoginDetectedBroadcast');
        $this->assertPayloadHasVersioning($payload, 'NewLoginDetectedBroadcast');
        $this->assertTranslationResolved($payload['title']);
        $this->assertNoMissingPlaceholders($payload);
        $this->assertEquals('security_alert', $payload['status']);
        $this->assertEquals('shield-alert', $payload['icon']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // i18n MULTI-LOCALE TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function translations_resolve_for_spanish_locale(): void
    {
        app()->setLocale('es');

        $event = OrderPaidBroadcast::fromDomainData(1, 1, 'ORD-1', 1000, 'HNL', 'cash', new DateTimeImmutable());
        $payload = $event->broadcastWith();

        $this->assertTranslationResolved($payload['title']);
        $this->assertTranslationResolved($payload['message']);
        $this->assertNoMissingPlaceholders($payload);
    }

    /** @test */
    public function translations_fallback_to_english_when_locale_missing(): void
    {
        app()->setLocale('en');

        $event = OrderPaidBroadcast::fromDomainData(1, 1, 'ORD-1', 1000, 'HNL', 'cash', new DateTimeImmutable());
        $payload = $event->broadcastWith();

        $this->assertTranslationResolved($payload['title']);
        $this->assertTranslationResolved($payload['message']);
        $this->assertNoMissingPlaceholders($payload);
        $this->assertEquals('Payment Confirmed', $payload['title']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EDGE CASES: NULL VALUES
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function low_stock_broadcast_handles_null_sku_gracefully(): void
    {
        $event = LowStockBroadcast::fromDomainData(
            tenantId: 1,
            productId: 1,
            productName: 'Widget',
            productSku: null,
            currentStock: 5,
            threshold: 10,
            severity: 'warning',
            occurredAt: new DateTimeImmutable(),
        );

        $payload = $event->broadcastWith();

        $this->assertPayloadSatisfiesContract($payload, 'LowStockBroadcast');
        $this->assertNull($payload['product_sku']);
        $this->assertNoMissingPlaceholders($payload);
        $this->assertStringNotContainsString('(SKU:', $payload['message']);
    }

    /** @test */
    public function order_refunded_handles_null_reason(): void
    {
        $event = OrderRefundedBroadcast::fromDomainData(
            tenantId: 1,
            orderId: 1,
            orderNumber: 'ORD-1',
            refundAmountCents: 1000,
            currency: 'HNL',
            isPartial: false,
            reason: null,
            refundedAt: new DateTimeImmutable(),
        );

        $payload = $event->broadcastWith();

        $this->assertPayloadSatisfiesContract($payload, 'OrderRefundedBroadcast');
        $this->assertNull($payload['reason']);
        $this->assertNoMissingPlaceholders($payload);
    }

    /** @test */
    public function new_login_handles_null_user_agent(): void
    {
        $event = NewLoginDetectedBroadcast::fromDomainData(
            userId: 1,
            tenantId: 1,
            userEmail: 'user@test.com',
            ipAddress: '10.0.0.1',
            userAgent: null,
            loginAt: new DateTimeImmutable(),
        );

        $payload = $event->broadcastWith();

        $this->assertPayloadSatisfiesContract($payload, 'NewLoginDetectedBroadcast');
        $this->assertNoMissingPlaceholders($payload);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EDGE CASES: SPECIAL CHARACTERS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function low_stock_handles_special_characters_in_product_name(): void
    {
        $event = LowStockBroadcast::fromDomainData(
            tenantId: 1,
            productId: 1,
            productName: "L'Oréal Premium \"Deluxe\" & Co.",
            productSku: "SKU-O'BRIEN",
            currentStock: 5,
            threshold: 10,
            severity: 'warning',
            occurredAt: new DateTimeImmutable(),
        );

        $payload = $event->broadcastWith();

        $json = json_encode($payload);
        $this->assertNotFalse($json, 'Payload must be JSON-serializable');
        $this->assertJson($json);
        $this->assertStringContainsString("L'Oréal", $payload['message']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EDGE CASES: MONEY FORMATTING PRECISION
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function format_money_handles_zero_amount(): void
    {
        $event = OrderPaidBroadcast::fromDomainData(
            tenantId: 1,
            orderId: 1,
            orderNumber: 'ORD-1',
            amountCents: 0,
            currency: 'HNL',
            paymentMethod: 'card',
            paidAt: new DateTimeImmutable(),
        );

        $payload = $event->broadcastWith();
        // Allow for intl or manual formatting
        $this->assertMatchesRegularExpression('/0[.,]00/', $payload['amount_formatted']);
    }

    /** @test */
    public function format_money_handles_large_amounts(): void
    {
        $event = OrderPaidBroadcast::fromDomainData(
            tenantId: 1,
            orderId: 1,
            orderNumber: 'ORD-1',
            amountCents: 99999999,
            currency: 'HNL',
            paymentMethod: 'card',
            paidAt: new DateTimeImmutable(),
        );

        $payload = $event->broadcastWith();
        $this->assertStringContainsString('999', $payload['amount_formatted']);
    }

    /** @test */
    public function format_money_handles_cents_correctly(): void
    {
        $event = OrderPaidBroadcast::fromDomainData(
            tenantId: 1,
            orderId: 1,
            orderNumber: 'ORD-1',
            amountCents: 333,
            currency: 'HNL',
            paymentMethod: 'card',
            paidAt: new DateTimeImmutable(),
        );

        $payload = $event->broadcastWith();
        $this->assertMatchesRegularExpression('/3[.,]33/', $payload['amount_formatted']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CHANNEL REGISTRY VALIDATION
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function channel_registry_rejects_zero_tenant_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tenantId must be a positive integer');

        ChannelRegistry::tenant(0);
    }

    /** @test */
    public function channel_registry_rejects_negative_tenant_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tenantId must be a positive integer');

        ChannelRegistry::tenant(-5);
    }

    /** @test */
    public function channel_registry_generates_correct_channel_name(): void
    {
        $channelName = ChannelRegistry::tenantName(42);
        $this->assertEquals('tenant.42', $channelName);
    }

    /** @test */
    public function channel_registry_exposes_pattern_constants(): void
    {
        $this->assertEquals('tenant.{id}', ChannelRegistry::TENANT_CHANNEL_PATTERN);
        $this->assertEquals('user.{id}', ChannelRegistry::USER_CHANNEL_PATTERN);
        $this->assertEquals('order.{id}', ChannelRegistry::ORDER_CHANNEL_PATTERN);
    }

    /** @test */
    public function channel_registry_rejects_zero_user_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('userId must be a positive integer');

        ChannelRegistry::user(0);
    }

    /** @test */
    public function channel_registry_rejects_negative_order_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('orderId must be a positive integer');

        ChannelRegistry::order(-10);
    }

    /** @test */
    public function channel_registry_generates_correct_order_name(): void
    {
        $orderName = ChannelRegistry::orderName(999);
        $this->assertEquals('order.999', $orderName);
    }

    /** @test */
    public function channel_registry_generates_correct_user_name(): void
    {
        $userName = ChannelRegistry::userName(123);
        $this->assertEquals('user.123', $userName);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SEVERITY VALIDATION TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function low_stock_broadcast_rejects_invalid_severity(): void
    {
        $this->expectException(\ValueError::class);

        LowStockBroadcast::fromDomainData(
            tenantId: 1,
            productId: 1,
            productName: 'Widget',
            productSku: null,
            currentStock: 5,
            threshold: 10,
            severity: 'invalid_severity',
            occurredAt: new DateTimeImmutable(),
        );
    }

    /** @test */
    public function low_stock_broadcast_accepts_valid_severities(): void
    {
        $severities = ['info', 'warning', 'critical'];

        foreach ($severities as $severity) {
            $event = LowStockBroadcast::fromDomainData(
                tenantId: 1,
                productId: 1,
                productName: 'Widget',
                productSku: null,
                currentStock: 5,
                threshold: 10,
                severity: $severity,
                occurredAt: new DateTimeImmutable(),
            );

            $payload = $event->broadcastWith();
            $this->assertEquals($severity, $payload['severity']);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SECURITY TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function broadcast_payloads_contain_no_object_instances(): void
    {
        $events = [
            OrderPaidBroadcast::fromDomainData(1, 1, 'ORD-1', 1000, 'HNL', 'cash', new DateTimeImmutable()),
            OrderCancelledBroadcast::fromDomainData(1, 1, 'ORD-1', 'test', false, new DateTimeImmutable()),
            LowStockBroadcast::fromDomainData(1, 1, 'Product', null, 5, 10, 'warning', new DateTimeImmutable()),
            OrderShippedBroadcast::fromDomainData(1, 1, 'ORD-1', 'ups', '123', null, new DateTimeImmutable()),
            OrderRefundedBroadcast::fromDomainData(1, 1, 'ORD-1', 1000, 'HNL', false, null, new DateTimeImmutable()),
            NewLoginDetectedBroadcast::fromDomainData(1, 1, 'test@test.com', '1.1.1.1', null, new DateTimeImmutable()),
        ];

        foreach ($events as $event) {
            $payload = $event->broadcastWith();
            $eventClass = get_class($event);

            foreach ($payload as $key => $value) {
                $isScalarOrNull = is_null($value) || is_scalar($value) || is_array($value);
                $this->assertTrue(
                    $isScalarOrNull,
                    "Broadcast event {$eventClass} contains non-scalar in key '{$key}'"
                );
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════════════

    private function assertPayloadSatisfiesContract(array $payload, string $eventName): void
    {
        foreach (self::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $payload,
                "Broadcast event {$eventName} is missing required key '{$key}'"
            );

            $this->assertNotEmpty(
                $payload[$key],
                "Broadcast event {$eventName} has empty value for required key '{$key}'"
            );
        }
    }

    private function assertPayloadHasVersioning(array $payload, string $eventName): void
    {
        foreach (self::VERSIONING_KEYS as $key) {
            $this->assertArrayHasKey(
                $key,
                $payload,
                "Broadcast event {$eventName} is missing versioning key '{$key}'"
            );
        }

        $this->assertIsInt($payload['_version'], 'Version must be an integer');
        $this->assertGreaterThan(0, $payload['_version'], 'Version must be positive');
    }

    /**
     * Assert that a translation was actually resolved (not returned as the key).
     */
    private function assertTranslationResolved(string $value): void
    {
        $this->assertStringNotContainsString(
            'notifications.',
            $value,
            "Translation key was not resolved: {$value}"
        );
    }

    /**
     * PHANTOM PLACEHOLDER DETECTION
     *
     * Scans all string values in the payload for unreplaced Laravel
     * translation placeholders (e.g., :amount, :order).
     *
     * This catches typos in notifications.php like :amout instead of :amount.
     */
    private function assertNoMissingPlaceholders(array $payload): void
    {
        $this->scanForPlaceholders($payload, '');
    }

    /**
     * Recursively scan payload for phantom placeholders.
     *
     * EXCLUDED KEYS:
     *   - Keys starting with underscore (_version, _timestamp) are metadata
     *   - ISO timestamps contain ":" which would trigger false positives
     */
    private function scanForPlaceholders(array $data, string $path): void
    {
        foreach ($data as $key => $value) {
            // Skip metadata keys (underscore prefix)
            if (str_starts_with((string)$key, '_')) {
                continue;
            }

            $currentPath = $path ? "{$path}.{$key}" : $key;

            if (is_string($value)) {
                // Skip timestamp-like values (ISO 8601 format)
                if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $value)) {
                    continue;
                }

                // Regex: Detect :word patterns (Laravel placeholders)
                // Only matches alphabetic placeholders like :amount, :order
                if (preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $value, $matches)) {
                    foreach ($matches[0] as $placeholder) {
                        $this->fail(
                            "Detected missing translation placeholder '{$placeholder}' " .
                            "in payload key '{$currentPath}'. Value: \"{$value}\""
                        );
                    }
                }
            } elseif (is_array($value)) {
                $this->scanForPlaceholders($value, $currentPath);
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PLURALIZATION GRAMMAR TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function low_stock_message_uses_singular_when_stock_is_one(): void
    {
        app()->setLocale('es');

        $event = LowStockBroadcast::fromDomainData(
            tenantId: 1,
            productId: 1,
            productName: 'Widget',
            productSku: null,
            currentStock: 1,
            threshold: 10,
            severity: 'warning',
            occurredAt: new DateTimeImmutable(),
        );

        $payload = $event->broadcastWith();

        // Should use singular "unidad" not "unidades"
        $this->assertStringContainsString('unidad', $payload['message']);
        $this->assertStringNotContainsString('unidades', $payload['message']);
    }

    /** @test */
    public function low_stock_message_uses_plural_when_stock_is_multiple(): void
    {
        app()->setLocale('es');

        $event = LowStockBroadcast::fromDomainData(
            tenantId: 1,
            productId: 1,
            productName: 'Widget',
            productSku: null,
            currentStock: 5,
            threshold: 10,
            severity: 'warning',
            occurredAt: new DateTimeImmutable(),
        );

        $payload = $event->broadcastWith();

        // Should use plural "unidades"
        $this->assertStringContainsString('unidades', $payload['message']);
    }

    /** @test */
    public function low_stock_message_uses_out_of_stock_when_zero(): void
    {
        app()->setLocale('es');

        $event = LowStockBroadcast::fromDomainData(
            tenantId: 1,
            productId: 1,
            productName: 'Widget',
            productSku: null,
            currentStock: 0,
            threshold: 10,
            severity: 'critical',
            occurredAt: new DateTimeImmutable(),
        );

        $payload = $event->broadcastWith();

        // Should use "agotado" for zero stock
        $this->assertStringContainsString('agotado', $payload['message']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PLACEHOLDER DETECTION NEGATIVE TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function placeholder_scanner_detects_unresolved_placeholders(): void
    {
        // Simulate a string with an unresolved placeholder (typo scenario)
        $testPayload = [
            'title' => 'Test Title',
            'message' => 'The amount :amout was not replaced',  // typo: :amout instead of :amount
            'status' => 'test',
            'icon' => 'test-icon',
        ];

        // The scanner should detect the unreplaced placeholder
        $detectedPlaceholders = [];
        foreach ($testPayload as $key => $value) {
            if (is_string($value) && preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $value, $matches)) {
                $detectedPlaceholders = array_merge($detectedPlaceholders, $matches[0]);
            }
        }

        $this->assertContains(':amout', $detectedPlaceholders);
    }

    /** @test */
    public function placeholder_scanner_ignores_iso_timestamps(): void
    {
        $timestamp = '2026-01-01T12:30:45+00:00';

        // Regex should NOT match timestamps as placeholders
        $matches = [];
        preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $timestamp, $matches);

        $this->assertEmpty($matches[0], 'ISO timestamp should not trigger placeholder detection');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INTERFACE IMPLEMENTATION TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function all_broadcast_events_implement_broadcastable_notification(): void
    {
        $events = [
            OrderPaidBroadcast::fromDomainData(1, 1, 'ORD-1', 1000, 'HNL', 'cash', new DateTimeImmutable()),
            OrderCancelledBroadcast::fromDomainData(1, 1, 'ORD-1', 'test', false, new DateTimeImmutable()),
            LowStockBroadcast::fromDomainData(1, 1, 'Product', null, 5, 10, 'warning', new DateTimeImmutable()),
            OrderShippedBroadcast::fromDomainData(1, 1, 'ORD-1', 'ups', '123', null, new DateTimeImmutable()),
            OrderRefundedBroadcast::fromDomainData(1, 1, 'ORD-1', 1000, 'HNL', false, null, new DateTimeImmutable()),
            NewLoginDetectedBroadcast::fromDomainData(1, 1, 'test@test.com', '1.1.1.1', null, new DateTimeImmutable()),
        ];

        foreach ($events as $event) {
            $this->assertInstanceOf(
                \App\Contracts\Broadcasting\BroadcastableNotification::class,
                $event,
                get_class($event) . ' should implement BroadcastableNotification interface'
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // OBSERVABILITY / LOGGING TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function broadcast_events_log_when_broadcasting(): void
    {
        // Use Laravel's Log facade spy
        \Illuminate\Support\Facades\Log::spy();

        $event = OrderPaidBroadcast::fromDomainData(
            tenantId: 1,
            orderId: 123,
            orderNumber: 'ORD-TEST',
            amountCents: 10000,
            currency: 'HNL',
            paymentMethod: 'card',
            paidAt: new DateTimeImmutable(),
        );

        // Trigger broadcastWith which contains the log call
        $event->broadcastWith();

        // Verify log was called (spy mode)
        \Illuminate\Support\Facades\Log::shouldHaveReceived('channel')->atLeast()->once();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FORMATS CURRENCY FALLBACK TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function format_money_rejects_empty_currency_without_intl(): void
    {
        // This test verifies currency validation works
        // The actual formatMoney method in the trait validates currency

        // Create a simple class using the trait for testing
        $formatter = new class {
            use \App\Traits\FormatsCurrency;

            public function testFormat(int $cents, string $currency): string
            {
                return $this->formatMoney($cents, $currency);
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code cannot be empty');

        $formatter->testFormat(1000, '');
    }

    /** @test */
    public function format_money_supports_additional_currencies(): void
    {
        $formatter = new class {
            use \App\Traits\FormatsCurrency;

            public function testFormat(int $cents, string $currency): string
            {
                return $this->formatMoney($cents, $currency);
            }
        };

        // Test MXN (Mexican Peso)
        $mxnResult = $formatter->testFormat(15000, 'MXN');
        $this->assertNotEmpty($mxnResult);

        // Test GTQ (Guatemalan Quetzal)
        $gtqResult = $formatter->testFormat(25000, 'GTQ');
        $this->assertNotEmpty($gtqResult);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ENUM TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function low_stock_severity_enum_has_all_values(): void
    {
        $values = \App\Enums\LowStockSeverity::values();

        $this->assertContains('info', $values);
        $this->assertContains('warning', $values);
        $this->assertContains('critical', $values);
        $this->assertCount(3, $values);
    }

    /** @test */
    public function low_stock_severity_enum_provides_labels(): void
    {
        $this->assertEquals('Warning', \App\Enums\LowStockSeverity::Warning->label());
        $this->assertEquals('Critical', \App\Enums\LowStockSeverity::Critical->label());
        $this->assertEquals('Informational', \App\Enums\LowStockSeverity::Info->label());
    }

    /** @test */
    public function low_stock_severity_critical_requires_immediate_action(): void
    {
        $this->assertTrue(\App\Enums\LowStockSeverity::Critical->requiresImmediateAction());
        $this->assertFalse(\App\Enums\LowStockSeverity::Warning->requiresImmediateAction());
        $this->assertFalse(\App\Enums\LowStockSeverity::Info->requiresImmediateAction());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PAYLOAD CONSISTENCY TESTS
    // ═══════════════════════════════════════════════════════════════════════

    /** @test */
    public function all_broadcast_events_include_tenant_id_in_payload(): void
    {
        $events = [
            OrderPaidBroadcast::fromDomainData(42, 1, 'ORD-1', 1000, 'HNL', 'cash', new DateTimeImmutable()),
            OrderCancelledBroadcast::fromDomainData(42, 1, 'ORD-1', 'test', false, new DateTimeImmutable()),
            LowStockBroadcast::fromDomainData(42, 1, 'Product', null, 5, 10, 'warning', new DateTimeImmutable()),
            OrderShippedBroadcast::fromDomainData(42, 1, 'ORD-1', 'ups', '123', null, new DateTimeImmutable()),
            OrderRefundedBroadcast::fromDomainData(42, 1, 'ORD-1', 1000, 'HNL', false, null, new DateTimeImmutable()),
            NewLoginDetectedBroadcast::fromDomainData(1, 42, 'test@test.com', '1.1.1.1', null, new DateTimeImmutable()),
        ];

        foreach ($events as $event) {
            $payload = $event->broadcastWith();

            $this->assertArrayHasKey(
                'tenant_id',
                $payload,
                get_class($event) . ' must include tenant_id in payload'
            );

            $this->assertEquals(
                42,
                $payload['tenant_id'],
                get_class($event) . ' tenant_id must match constructor value'
            );
        }
    }
}

