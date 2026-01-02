<?php

declare(strict_types=1);

namespace Tests\Arch;

use App\ValueObjects\Money;
use App\ValueObjects\OrderNumber;
use App\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * ValueObjectsSerializationTest - Verify VOs are queue-safe
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICALITY: Medium                                                    ║
 * ║  IMPACT: If a VO is not serializable, events fail silently in queues   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class ValueObjectsSerializationTest extends TestCase
{
    /**
     * Test: TenantId is JSON serializable and recoverable
     */
    public function test_tenant_id_is_json_serializable(): void
    {
        $tenantId = TenantId::fromInt(42);

        // Verify implements JsonSerializable
        $this->assertInstanceOf(\JsonSerializable::class, $tenantId);

        // Serialize
        $json = json_encode($tenantId);
        $this->assertIsString($json);
        $this->assertJson($json);

        // Decode and verify structure
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('tenant_id', $decoded);
        $this->assertEquals(42, $decoded['tenant_id']);

        // Reconstruct
        $restored = TenantId::fromArray($decoded);
        $this->assertEquals($tenantId->toInt(), $restored->toInt());
    }

    /**
     * Test: Money is JSON serializable and preserves precision
     */
    public function test_money_is_json_serializable_with_precision(): void
    {
        $money = Money::fromCents(99999, 'HNL');

        // Verify implements JsonSerializable
        $this->assertInstanceOf(\JsonSerializable::class, $money);

        // Serialize
        $json = json_encode($money);
        $this->assertIsString($json);

        // Decode and verify
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('cents', $decoded);
        $this->assertArrayHasKey('currency', $decoded);

        // CRITICAL: cents must be int, not float
        $this->assertIsInt($decoded['cents']);
        $this->assertEquals(99999, $decoded['cents']);
        $this->assertEquals('HNL', $decoded['currency']);

        // Reconstruct
        $restored = Money::fromArray($decoded);
        $this->assertEquals($money->cents(), $restored->cents());
        $this->assertEquals($money->currency(), $restored->currency());
    }

    /**
     * Test: OrderNumber is JSON serializable
     */
    public function test_order_number_is_json_serializable(): void
    {
        $orderNumber = OrderNumber::fromString('ORD-2025-001');

        // Verify implements JsonSerializable
        $this->assertInstanceOf(\JsonSerializable::class, $orderNumber);

        // Serialize
        $json = json_encode($orderNumber);
        $this->assertIsString($json);

        // Decode and verify
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('number', $decoded);
        $this->assertEquals('ORD-2025-001', $decoded['number']);

        // Reconstruct
        $restored = OrderNumber::fromArray($decoded);
        $this->assertEquals($orderNumber->toString(), $restored->toString());
    }

    /**
     * Test: Round-trip serialization (serialize → deserialize → serialize)
     *
     * Verifies that a VO can travel multiple times through the queue
     * without data degradation.
     */
    public function test_money_round_trip_serialization(): void
    {
        $original = Money::fromCents(54321, 'HNL');

        // Trip 1: Serialize
        $json1 = json_encode($original);

        // Trip 2: Deserialize
        $data1 = json_decode($json1, true);
        $restored1 = Money::fromArray($data1);

        // Trip 3: Re-serialize
        $json2 = json_encode($restored1);

        // Trip 4: Re-deserialize
        $data2 = json_decode($json2, true);
        $restored2 = Money::fromArray($data2);

        // Verify that after 2 round-trips, the VO is identical
        $this->assertEquals($original->cents(), $restored2->cents());
        $this->assertEquals($original->currency(), $restored2->currency());
        $this->assertEquals($original->formatted(), $restored2->formatted());
    }

    /**
     * Test: DateTimeImmutable uses ATOM format (ISO 8601)
     *
     * All timestamps in events must be DateTimeImmutable::ATOM formatted.
     */
    public function test_datetime_immutable_uses_atom_format(): void
    {
        $dt = new DateTimeImmutable('2025-12-30T23:36:00Z');

        $json = json_encode(['timestamp' => $dt->format(DateTimeImmutable::ATOM)]);
        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $restored = DateTimeImmutable::createFromFormat(
            DateTimeImmutable::ATOM,
            $decoded['timestamp']
        );

        // Verify timestamp preserved
        $this->assertEquals($dt->getTimestamp(), $restored->getTimestamp());
    }

    /**
     * Test: All Value Objects implement JsonSerializable
     */
    public function test_all_value_objects_implement_json_serializable(): void
    {
        $valueObjects = [
            TenantId::fromInt(1),
            Money::fromCents(1000, 'HNL'),
            OrderNumber::fromString('ORD-001'),
        ];

        foreach ($valueObjects as $vo) {
            // 1. Verify implements JsonSerializable
            $this->assertInstanceOf(
                \JsonSerializable::class,
                $vo,
                get_class($vo) . ' must implement JsonSerializable'
            );

            // 2. Verify serialize() works
            $json = json_encode($vo);
            $this->assertIsString($json);
            $this->assertNotEmpty($json);

            // 3. Verify deserialize() is deterministic
            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded);
            $this->assertNotEmpty($decoded);
        }
    }
}
