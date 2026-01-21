<?php

declare(strict_types=1);

namespace Tests\Architecture\Contracts;

use App\Domain\Auth\Events\UserPasswordChanged;
use App\Domain\User\Events\UserProfileUpdated;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * EventSchemaTest - Contract Testing for Domain Events
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURPOSE: Ensure PHP event classes produce payloads matching JSON Schema ║
 * ║  FAILS IF: Developer changes event structure without updating schema    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * This test acts as a CONTRACT GUARDIAN. It validates that:
 * 1. Every domain event has a corresponding JSON Schema file
 * 2. The event's toPayload() output matches the schema's required structure
 * 3. No undocumented fields are being serialized
 */
class EventSchemaTest extends TestCase
{
    private const SCHEMA_BASE_PATH = __DIR__.'/../../../resources/schemas';

    /**
     * @dataProvider eventSchemaProvider
     */
    public function test_event_payload_matches_json_schema(
        string $eventClass,
        array $constructorArgs,
        string $schemaPath
    ): void {
        // 1. Verify schema file exists
        $fullSchemaPath = self::SCHEMA_BASE_PATH.$schemaPath;
        $this->assertFileExists(
            $fullSchemaPath,
            "JSON Schema file missing for {$eventClass}. Expected: {$schemaPath}"
        );

        // 2. Instantiate the event
        $event = new $eventClass(...$constructorArgs);

        // 3. Serialize to payload
        $this->assertTrue(
            method_exists($event, 'toPayload'),
            "Event {$eventClass} must implement toPayload() method"
        );

        $this->assertTrue(
            method_exists($event, 'eventName'),
            "Event {$eventClass} must implement eventName() method"
        );

        $this->assertTrue(
            method_exists($event, 'schemaVersion'),
            "Event {$eventClass} must implement schemaVersion() method"
        );

        $payload = $event->toPayload();
        $eventName = $event->eventName();
        $schemaVersion = $event->schemaVersion();

        // 4. Build full envelope as stored
        $envelope = [
            'event_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            'type' => $eventName,
            'schema_version' => $schemaVersion,
            'payload' => $payload,
        ];

        // 5. Load and parse schema
        $schemaJson = file_get_contents($fullSchemaPath);
        $schema = json_decode($schemaJson, true);

        $this->assertNotNull($schema, "Failed to parse JSON Schema: {$schemaPath}");

        // 6. Validate structure (simplified validation without external library)
        $this->validateAgainstSchema($envelope, $schema, $eventClass);
    }

    /**
     * Provides test cases for each domain event.
     */
    public static function eventSchemaProvider(): array
    {
        return [
            'UserPasswordChanged v1' => [
                UserPasswordChanged::class,
                [
                    'userId' => '550e8400-e29b-41d4-a716-446655440000',
                    'tenantId' => '660e8400-e29b-41d4-a716-446655440001',
                    'sessionInvalidated' => true,
                    'occurredAt' => new DateTimeImmutable('2026-01-17T20:00:00+00:00'),
                ],
                '/auth/user_password_changed.v1.json',
            ],
            'UserProfileUpdated v1' => [
                UserProfileUpdated::class,
                [
                    'userId' => '550e8400-e29b-41d4-a716-446655440000',
                    'tenantId' => '660e8400-e29b-41d4-a716-446655440001',
                    'emailChanged' => false,
                    'occurredAt' => new DateTimeImmutable('2026-01-17T20:00:00+00:00'),
                ],
                '/auth/user_profile_updated.v1.json',
            ],
        ];
    }

    /**
     * Simplified schema validation (for production, use opis/json-schema).
     */
    private function validateAgainstSchema(array $data, array $schema, string $eventClass): void
    {
        // Validate required root properties exist
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                $this->assertArrayHasKey(
                    $requiredField,
                    $data,
                    "Event {$eventClass} envelope missing required field: {$requiredField}"
                );
            }
        }

        // Validate payload required properties
        if (isset($schema['properties']['payload']['required'])) {
            foreach ($schema['properties']['payload']['required'] as $requiredField) {
                $this->assertArrayHasKey(
                    $requiredField,
                    $data['payload'],
                    "Event {$eventClass} payload missing required field: {$requiredField}"
                );
            }
        }

        // Validate additionalProperties: false (no extra fields)
        if (isset($schema['properties']['payload']['additionalProperties'])
            && $schema['properties']['payload']['additionalProperties'] === false) {

            $allowedFields = array_keys($schema['properties']['payload']['properties'] ?? []);
            $actualFields = array_keys($data['payload']);
            $extraFields = array_diff($actualFields, $allowedFields);

            $this->assertEmpty(
                $extraFields,
                "Event {$eventClass} has undocumented payload fields: ".implode(', ', $extraFields)
            );
        }

        // Validate type constants
        if (isset($schema['properties']['type']['const'])) {
            $this->assertEquals(
                $schema['properties']['type']['const'],
                $data['type'],
                "Event {$eventClass} type mismatch"
            );
        }

        // Validate schema version
        if (isset($schema['properties']['schema_version']['const'])) {
            $this->assertEquals(
                $schema['properties']['schema_version']['const'],
                $data['schema_version'],
                "Event {$eventClass} schema_version mismatch"
            );
        }
    }
}
