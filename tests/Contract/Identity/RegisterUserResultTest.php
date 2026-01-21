<?php

declare(strict_types=1);

namespace Tests\Contract\Identity;

use App\Application\Identity\Results\RegisterUserResult;
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
            requiresVerification: true,
            createdAt: '2026-01-15T22:00:00+00:00',
        );

        // 2. Act: Serialize
        $json = json_decode(json_encode($result), true);

        // 3. Assert: Structural Verification
        $this->assertIsArray($json);

        // Verify Keys Exist
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('tenantId', $json);
        $this->assertArrayHasKey('email', $json);
        $this->assertArrayHasKey('requiresVerification', $json);
        $this->assertArrayHasKey('createdAt', $json);

        // Verify Types (CHANGED: int → string for UUIDs)
        $this->assertIsString($json['id']);
        $this->assertIsString($json['tenantId']);
        $this->assertIsString($json['email']);
        $this->assertIsBool($json['requiresVerification']);
        $this->assertIsString($json['createdAt']);
    }

    public function test_register_result_array_serialization(): void
    {
        $result = new RegisterUserResult(
            id: '01942f8a-1234-7890-abcd-ef1234567890',
            tenantId: '01942f8b-5678-7890-abcd-ef1234567890',
            email: 'test@example.com',
            requiresVerification: true,
            createdAt: '2026-01-15T22:00:00+00:00',
        );

        $array = $result->toArray();

        $this->assertSame('01942f8a-1234-7890-abcd-ef1234567890', $array['id']);
        $this->assertSame('01942f8b-5678-7890-abcd-ef1234567890', $array['tenantId']);
        $this->assertSame('test@example.com', $array['email']);
        $this->assertTrue($array['requiresVerification']);
        $this->assertSame('2026-01-15T22:00:00+00:00', $array['createdAt']);
    }

    public function test_register_result_from_array_hydration(): void
    {
        $data = [
            'id' => '01942f8a-1234-7890-abcd-ef1234567890',
            'tenantId' => '01942f8b-5678-7890-abcd-ef1234567890',
            'email' => 'hydrated@example.com',
            'requiresVerification' => false,
            'createdAt' => '2026-01-15T22:00:00+00:00',
        ];

        $result = RegisterUserResult::fromArray($data);

        $this->assertSame($data['id'], $result->id);
        $this->assertSame($data['tenantId'], $result->tenantId);
        $this->assertSame($data['email'], $result->email);
        $this->assertFalse($result->requiresVerification);
    }
}
