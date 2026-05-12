<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Domain\Shared\ValueObjects\TenantId;
use App\Events\Audit\SalesExportCreated;
use App\Events\Order\OrderShipped;
use App\Events\Security\FailedLoginAttempt;
use App\Events\Security\SensitiveDataAccessed;
use App\Events\Security\TenantSuspended;
use App\ValueObjects\AuditContext;
use App\ValueObjects\IpAddress;
use DateTimeImmutable;
use Tests\TestCase;

/**
 * EventSerializationTest - Queue Integrity Verification
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Guarantee that events survive queue serialization cycle       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  WHAT THIS TEST VALIDATES:                                              ║
 * ║    1. toPrimitives() produces valid JSON-serializable arrays            ║
 * ║    2. fromPrimitives() reconstructs identical event state               ║
 * ║    3. No data loss during round-trip serialization                      ║
 * ║                                                                          ║
 * ║  WHY THIS MATTERS:                                                      ║
 * ║    - Events are serialized to JSON before entering Redis queue          ║
 * ║    - Workers deserialize events to execute listeners                    ║
 * ║    - Any data loss = silent bugs in production                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class EventSerializationTest extends TestCase
{
    /**
     * @test
     *
     * @group events
     * @group serialization
     */
    public function sales_export_created_survives_serialization(): void
    {
        $tenantId = TenantId::fromString('00000000-0000-0000-0000-000000000042');
        $occurredAt = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $original = new SalesExportCreated(
            exportId: 'exp_abc123',
            tenantId: $tenantId,
            userId: 1,
            format: 'xlsx',
            criteriaHash: 'hash_xyz',
            criteriaDetails: ['date_from' => '2026-01-01', 'date_to' => '2026-01-31'],
            occurredAt: $occurredAt,
            auditContext: new AuditContext(
                actorId: 1,
                ipAddress: new IpAddress('192.168.1.100'),
                userAgent: 'Mozilla/5.0',
                occurredAt: $occurredAt,
            ),
        );

        $primitives = $original->toPrimitives();
        $reconstituted = SalesExportCreated::fromPrimitives($primitives);

        $this->assertEquals($original->exportId, $reconstituted->exportId);
        $this->assertEquals($original->tenantId->toString(), $reconstituted->tenantId->toString());
        $this->assertEquals($original->userId, $reconstituted->userId);
        $this->assertEquals($original->format, $reconstituted->format);
        $this->assertEquals($original->criteriaHash, $reconstituted->criteriaHash);
        $this->assertEquals($original->criteriaDetails, $reconstituted->criteriaDetails);
    }

    /**
     * @test
     *
     * @group events
     * @group serialization
     */
    public function failed_login_attempt_survives_serialization(): void
    {
        $context = new AuditContext(
            actorId: null,
            ipAddress: IpAddress::fromString('10.0.0.1')->anonymize(),
            userAgent: 'curl/7.68.0',
            occurredAt: new DateTimeImmutable,
        );

        $original = new FailedLoginAttempt(
            email: 'attacker@example.com',
            tenantId: TenantId::fromInt(5),
            context: $context,
            failureReason: 'invalid_password',
        );

        $primitives = $original->toPrimitives();
        $reconstituted = FailedLoginAttempt::fromPrimitives($primitives);

        $this->assertEquals($original->email, $reconstituted->email);
        $this->assertEquals($original->tenantId->toInt(), $reconstituted->tenantId->toInt());
        $this->assertEquals($original->failureReason, $reconstituted->failureReason);
    }

    /**
     * @test
     *
     * @group events
     * @group serialization
     */
    public function sensitive_data_accessed_survives_serialization(): void
    {
        $context = new AuditContext(
            actorId: 99,
            ipAddress: IpAddress::fromString('172.16.0.50'),
            userAgent: 'AdminPanel/2.0',
            occurredAt: new DateTimeImmutable('2026-01-01T15:30:00+00:00'),
        );

        $original = new SensitiveDataAccessed(
            actorId: 99,
            actorEmail: 'admin@conectahn.com',
            tenantId: TenantId::fromInt(1),
            resource: 'customer_export',
            action: 'download',
            context: $context,
            reason: 'Monthly audit report',
            affectedRecords: 1500,
        );

        $primitives = $original->toPrimitives();
        $reconstituted = SensitiveDataAccessed::fromPrimitives($primitives);

        $this->assertEquals($original->actorId, $reconstituted->actorId);
        $this->assertEquals($original->actorEmail, $reconstituted->actorEmail);
        $this->assertEquals($original->tenantId->toInt(), $reconstituted->tenantId->toInt());
        $this->assertEquals($original->resource, $reconstituted->resource);
        $this->assertEquals($original->action, $reconstituted->action);
        $this->assertEquals($original->reason, $reconstituted->reason);
        $this->assertEquals($original->affectedRecords, $reconstituted->affectedRecords);
    }

    /**
     * @test
     *
     * @group events
     * @group serialization
     */
    public function tenant_suspended_survives_serialization(): void
    {
        $suspendedUntil = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
        $context = new AuditContext(
            actorId: 1,
            ipAddress: IpAddress::fromString('192.168.0.1'),
            userAgent: 'AdminCLI/1.0',
            occurredAt: new DateTimeImmutable,
        );

        $original = new TenantSuspended(
            tenantId: TenantId::fromInt(77),
            tenantSlug: 'bad-tenant',
            tenantName: 'Bad Tenant Inc',
            reason: 'terms_violation',
            context: $context,
            suspendedById: 1,
            suspendedUntil: $suspendedUntil,
        );

        $primitives = $original->toPrimitives();
        $reconstituted = TenantSuspended::fromPrimitives($primitives);

        $this->assertEquals($original->tenantId->toInt(), $reconstituted->tenantId->toInt());
        $this->assertEquals($original->tenantSlug, $reconstituted->tenantSlug);
        $this->assertEquals($original->tenantName, $reconstituted->tenantName);
        $this->assertEquals($original->reason, $reconstituted->reason);
        $this->assertEquals($original->suspendedById, $reconstituted->suspendedById);
        $this->assertEquals(
            $original->suspendedUntil->format('c'),
            $reconstituted->suspendedUntil->format('c')
        );
    }

    /**
     * @test
     *
     * @group events
     * @group serialization
     */
    public function tenant_suspended_handles_permanent_suspension(): void
    {
        $context = new AuditContext(
            actorId: null,
            ipAddress: IpAddress::fromString('127.0.0.1'),
            userAgent: 'System/FraudDetection',
            occurredAt: new DateTimeImmutable,
        );

        $original = new TenantSuspended(
            tenantId: TenantId::fromInt(99),
            tenantSlug: 'fraud-tenant',
            tenantName: 'Fraudulent Business',
            reason: 'fraud_detected',
            context: $context,
            suspendedById: null,
            suspendedUntil: null, // Permanent
        );

        $primitives = $original->toPrimitives();
        $reconstituted = TenantSuspended::fromPrimitives($primitives);

        $this->assertTrue($reconstituted->isPermanent());
        $this->assertTrue($reconstituted->isSystemSuspension());
        $this->assertNull($reconstituted->suspendedById);
        $this->assertNull($reconstituted->suspendedUntil);
    }

    /**
     * @test
     *
     * @group events
     * @group serialization
     */
    public function order_shipped_includes_tracking_data(): void
    {
        // OrderShipped no longer has trackingUrl() - it exposes raw data
        $event = OrderShipped::create(
            tenantId: 1,
            orderId: 123,
            orderNumber: 'ORD-2026-00001',
            carrier: 'fedex',
            trackingNumber: '123456789012',
            estimatedDelivery: '2026-01-15',
        );

        $primitives = $event->toPrimitives();
        $reconstituted = OrderShipped::fromPrimitives($primitives);

        // Verify domain data is preserved (not URL generation)
        $this->assertEquals('fedex', $reconstituted->carrier());
        $this->assertEquals('123456789012', $reconstituted->trackingNumber());
        $this->assertEquals('2026-01-15', $reconstituted->estimatedDelivery);
    }

    /**
     * @test
     *
     * @group events
     * @group serialization
     */
    public function primitives_are_json_serializable(): void
    {
        $context = new AuditContext(
            actorId: 1,
            ipAddress: IpAddress::fromString('10.0.0.1'),
            userAgent: 'Test/1.0',
            occurredAt: new DateTimeImmutable,
        );

        $event = new SensitiveDataAccessed(
            actorId: 1,
            actorEmail: 'test@test.com',
            tenantId: TenantId::fromInt(1),
            resource: 'test',
            action: 'view',
            context: $context,
        );

        $primitives = $event->toPrimitives();
        $json = json_encode($primitives);

        $this->assertNotFalse($json, 'Primitives must be JSON-serializable');
        $this->assertJson($json);

        // Verify round-trip through JSON
        $decoded = json_decode($json, true);
        $reconstituted = SensitiveDataAccessed::fromPrimitives($decoded);

        $this->assertEquals($event->actorId, $reconstituted->actorId);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CHAOS TESTING: Robustness under corrupt/degraded data
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * @group events
     * @group robustness
     * @group chaos
     *
     * CHAOS TEST: Verify system survives missing non-critical keys.
     *
     * Simulates: A message from an older version of the event, or partial
     * data corruption in Redis that strips some keys from the payload.
     *
     * Expected: The system uses default values and does NOT throw
     * "Undefined array key" warnings.
     */
    public function it_reconstitutes_gracefully_with_missing_keys(): void
    {
        // Minimal payload: only the absolutely required key (tenant_id)
        $degradedPayload = [
            'tenant_id' => 42,
            // Missing: order_id, order_number, carrier, tracking_number,
            // estimated_delivery, event_id, occurred_at, correlation_id
        ];

        // Should NOT throw Undefined array key exception
        $event = OrderShipped::fromPrimitives($degradedPayload);

        // Verify defaults were applied correctly via toPrimitives()
        $primitives = $event->toPrimitives();

        $this->assertEquals(42, $primitives['tenant_id']);
        $this->assertEquals(0, $primitives['order_id']);
        $this->assertEquals('UNKNOWN', $primitives['order_number']);
        $this->assertEquals('unknown', $event->carrier());
        $this->assertEquals('', $event->trackingNumber());
        $this->assertNull($event->estimatedDelivery);

        // Metadata should generate fresh values when not provided
        $this->assertNotEmpty($event->eventId());
        $this->assertNotEmpty($event->correlationId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->occurredAt());
    }

    /**
     * @test
     *
     * @group events
     * @group robustness
     * @group chaos
     *
     * CHAOS TEST: Verify system ignores extraneous/malicious keys.
     *
     * Simulates: A payload injection attack or legacy fields from an
     * older version of the event schema that should be silently ignored.
     *
     * Expected: The object is created correctly, extra keys are discarded,
     * and no security vulnerabilities are introduced.
     */
    public function it_ignores_extraneous_payload_data(): void
    {
        $noisyPayload = [
            // Valid keys
            'tenant_id' => 1,
            'order_id' => 999,
            'order_number' => 'ORD-CLEAN',
            'carrier' => 'dhl',
            'tracking_number' => 'ABC123',
            'estimated_delivery' => '2026-02-01',
            'event_id' => 'evt_clean',
            'occurred_at' => '2026-01-01T12:00:00+00:00',
            'correlation_id' => 'corr_clean',

            // NOISE: Extraneous keys that should be ignored
            'hack_attempt' => true,
            'sql_injection' => "'; DROP TABLE orders; --",
            'legacy_field' => 123,
            '__internal_secret' => 'password123',
            'customer_ssn' => '123-45-6789', // PII that shouldn't exist
            'admin_override' => true,
        ];

        // Should NOT throw any errors
        $event = OrderShipped::fromPrimitives($noisyPayload);

        // Verify valid data was processed correctly via toPrimitives()
        $primitives = $event->toPrimitives();

        $this->assertEquals(1, $primitives['tenant_id']);
        $this->assertEquals(999, $primitives['order_id']);
        $this->assertEquals('ORD-CLEAN', $primitives['order_number']);
        $this->assertEquals('dhl', $event->carrier());
        $this->assertEquals('ABC123', $event->trackingNumber());

        // Verify metadata was restored correctly
        $this->assertEquals('evt_clean', $event->eventId());
        $this->assertEquals('corr_clean', $event->correlationId());

        // Verify no extraneous properties were added
        // (readonly class prevents dynamic properties anyway)
        $reflection = new \ReflectionClass($event);
        $properties = $reflection->getProperties();

        // Event should only have expected properties, not injected ones
        $propertyNames = array_map(fn ($p) => $p->getName(), $properties);

        $this->assertNotContains('hack_attempt', $propertyNames);
        $this->assertNotContains('sql_injection', $propertyNames);
        $this->assertNotContains('legacy_field', $propertyNames);
        $this->assertNotContains('customer_ssn', $propertyNames);
    }

    /**
     * @test
     *
     * @group events
     * @group robustness
     *
     * BOUNDARY TEST: Verify fail-fast on missing critical identifier.
     *
     * tenant_id is the only truly required field for multi-tenant isolation.
     * Without it, we cannot route the event correctly.
     */
    public function it_fails_fast_on_missing_tenant_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required key: tenant_id');

        $payloadWithoutTenantId = [
            'order_id' => 123,
            'order_number' => 'ORD-001',
            'carrier' => 'ups',
        ];

        OrderShipped::fromPrimitives($payloadWithoutTenantId);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ADDITIONAL COVERAGE: NewOrderReceived and PaymentApproved
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     *
     * @group events
     * @group serialization
     */
    public function new_order_received_survives_serialization(): void
    {
        // Signature: create(tenantId, orderId, orderNumber, itemCount, totalAmountCents, customerId, customerName, currency)
        $original = \App\Events\Order\NewOrderReceived::create(
            tenantId: 10,
            orderId: 500,
            orderNumber: 'ORD-2026-TEST',
            itemCount: 5,
            totalAmountCents: 250000,
            customerId: 99,
            customerName: 'Test Customer',
            currency: 'HNL',
        );

        $primitives = $original->toPrimitives();

        // Verify JSON serializable
        $json = json_encode($primitives);
        $this->assertNotFalse($json, 'NewOrderReceived primitives must be JSON-serializable');

        $reconstituted = \App\Events\Order\NewOrderReceived::fromPrimitives($primitives);

        // Verify critical data preserved
        $this->assertEquals(10, $reconstituted->tenantId()->toInt());
        $this->assertEquals(500, $reconstituted->aggregateId());
        $this->assertEquals('ORD-2026-TEST', $reconstituted->orderNumber->toString());
        $this->assertEquals('Test Customer', $reconstituted->customerName);
        $this->assertEquals(5, $reconstituted->itemCount);
    }

    /**
     * @test
     *
     * @group events
     * @group serialization
     */
    public function payment_approved_survives_serialization(): void
    {
        // Signature: create(tenantId, orderId, orderNumber, transactionId, gatewayName, amountCents, currency)
        $original = \App\Events\Payment\PaymentApproved::create(
            tenantId: 15,
            orderId: 600,
            orderNumber: 'ORD-PAY-TEST',
            transactionId: 'txn_abc123',
            gatewayName: 'stripe',
            amountCents: 175000,
            currency: 'USD',
        );

        $primitives = $original->toPrimitives();

        // Verify JSON serializable
        $json = json_encode($primitives);
        $this->assertNotFalse($json, 'PaymentApproved primitives must be JSON-serializable');

        $reconstituted = \App\Events\Payment\PaymentApproved::fromPrimitives($primitives);

        // Verify critical data preserved
        $this->assertEquals(15, $reconstituted->tenantId()->toInt());
        $this->assertEquals(600, $reconstituted->aggregateId());
        $this->assertEquals('txn_abc123', $reconstituted->transactionId);
        $this->assertEquals('stripe', $reconstituted->gatewayName);
    }
}
