<?php

declare(strict_types=1);

namespace Tests\Contract\Identity;

use App\Application\Identity\Results\RegisterUserResult;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * RegisterUserResultTest - Contract Test for Write API
 *
 * UPDATED: Assertions changed from int to string to support UUID identifiers.
 * This ensures the JSON contract remains stable for API consumers.
 */
class RegisterUserResultTest extends TestCase
{
    public function test_register_result_serialization_contract(): void
    {
        // 1. Arrange: Instantiate with UUID Data (Production Format)
        $result = new RegisterUserResult(
            id: '01942f8a-1234-7890-abcd-ef1234567890',
            tenantId: '01942f8b-5678-7890-abcd-ef1234567890',
            email: 'test@example.com',
            registeredAt: CarbonImmutable::parse('2026-01-15T22:00:00+00:00'),
        );

        // 2. Act: Serialize
        $json = json_decode(json_encode($result), true);

        // 3. Assert: Structural Verification
        $this->assertIsArray($json);

        // Verify Keys Exist
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('tenantId', $json);
        $this->assertArrayHasKey('email', $json);
        $this->assertArrayHasKey('registeredAt', $json);

        // Verify Types (CHANGED: int → string for UUIDs)
        $this->assertIsString($json['id']);
        $this->assertIsString($json['tenantId']);
        $this->assertIsString($json['email']);
        $this->assertIsString($json['registeredAt']);
    }

    public function test_register_result_array_serialization(): void
    {
        $result = new RegisterUserResult(
            id: '01942f8a-1234-7890-abcd-ef1234567890',
            tenantId: '01942f8b-5678-7890-abcd-ef1234567890',
            email: 'test@example.com',
            registeredAt: CarbonImmutable::parse('2026-01-15T22:00:00+00:00'),
        );

        $array = json_decode(json_encode($result), true);

        $this->assertSame('01942f8a-1234-7890-abcd-ef1234567890', $array['id']);
        $this->assertSame('01942f8b-5678-7890-abcd-ef1234567890', $array['tenantId']);
        $this->assertSame('test@example.com', $array['email']);
        $this->assertArrayHasKey('registeredAt', $array);
    }

    public function test_register_result_from_array_hydration(): void
    {
        $registeredAt = CarbonImmutable::parse('2026-01-15T22:00:00+00:00');
        $result = new RegisterUserResult(
            id: '01942f8a-1234-7890-abcd-ef1234567890',
            tenantId: '01942f8b-5678-7890-abcd-ef1234567890',
            email: 'hydrated@example.com',
            registeredAt: $registeredAt,
        );

        $this->assertSame('01942f8a-1234-7890-abcd-ef1234567890', $result->id);
        $this->assertSame('01942f8b-5678-7890-abcd-ef1234567890', $result->tenantId);
        $this->assertSame('hydrated@example.com', $result->email);
        $this->assertTrue($result->registeredAt->equalTo($registeredAt));
    }
}
