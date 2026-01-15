<?php

declare(strict_types=1);

namespace Tests\Contract\Identity;

use App\Application\Identity\Results\RegisterUserResult;
use Tests\TestCase;

/**
 * RegisterUserResultTest
 * 
 * Ensures the Write API contract (JSON Structure) remains immutable.
 */
class RegisterUserResultTest extends TestCase
{
    public function test_register_result_serialization_contract(): void
    {
        // 1. Arrange: Instantiate with Dummy Data
        $result = new RegisterUserResult(
            id: 999,
            tenantId: 888,
            email: 'test@example.com',
            requiresVerification: true
        );

        // 2. Act: Serialize
        $json = json_decode(json_encode($result), true);

        // 3. Assert: Structural Verification
        $this->assertIsArray($json);

        // Verify Keys
        // Note: Assuming public properties serialize as their names (camelCase)
        // If snake_case is forced by a serializer, this test will catch the mismatch.
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('tenantId', $json);
        $this->assertArrayHasKey('email', $json);
        $this->assertArrayHasKey('requiresVerification', $json);

        // Verify Types
        $this->assertIsInt($json['id']);
        $this->assertIsInt($json['tenantId']);
        $this->assertIsString($json['email']);
        $this->assertIsBool($json['requiresVerification']);
    }
}
