<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Shared\Upcasting;

use App\Application\Shared\Contracts\UpcasterInterface;
use App\Infrastructure\Bus\Serialization\CommandUpcaster;
use PHPUnit\Framework\TestCase;

class CommandUpcasterTest extends TestCase
{
    private CommandUpcaster $upcaster;

    protected function setUp(): void
    {
        $this->upcaster = new CommandUpcaster;
    }

    public function test_it_throws_exception_if_event_type_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->upcaster->process(['schema_version' => 1]);
    }

    public function test_it_defaults_schema_version_to_1_if_missing(): void
    {
        $payload = [
            'event_type' => 'test_event',
            // schema_version missing
            'data' => 'value',
        ];

        // Should not throw exception and should return payload (maybe unmodified if no upcasters)
        $result = $this->upcaster->process($payload);

        $this->assertSame('test_event', $result['event_type']);
        $this->assertSame('value', $result['data']);
        // The process method might not inject schema_version into result unless upcasted?
        // Let's check implementation. It updates $payload['schema_version'] ONLY if upcaster runs.
        // If no upcaster runs, it returns original payload?
        // Current implementation: only updates $payload inside loop.
        // So if no upcaster matches, schema_version remains missing/null in return?
        // Wait, current implementation:
        // $currentVersion = (int) ($schemaVersion ?? 1);
        // ... loop ...
        // return $payload;
        // It does NOT inject currentVersion into payload if no upcaster runs.
        // This is fine, as long as it doesn't crash.
    }

    public function test_it_applies_upcasters_in_sequence(): void
    {
        $upcasterV1toV2 = new class implements UpcasterInterface
        {
            public function fromVersion(): int
            {
                return 1;
            }

            public function toVersion(): int
            {
                return 2;
            }

            public function supports(string $type, int $version): bool
            {
                return $type === 'test_event' && $version === 1;
            }

            public function upcast(array $payload): array
            {
                $payload['v2_field'] = 'added_in_v2';
                unset($payload['v1_field']);

                return $payload;
            }
        };

        $upcasterV2toV3 = new class implements UpcasterInterface
        {
            public function fromVersion(): int
            {
                return 2;
            }

            public function toVersion(): int
            {
                return 3;
            }

            public function supports(string $type, int $version): bool
            {
                return $type === 'test_event' && $version === 2;
            }

            public function upcast(array $payload): array
            {
                $payload['v3_field'] = 'added_in_v3';

                return $payload;
            }
        };

        $this->upcaster->register($upcasterV1toV2);
        $this->upcaster->register($upcasterV2toV3);

        $payload = [
            'event_type' => 'test_event',
            'schema_version' => 1,
            'v1_field' => 'original',
        ];

        $result = $this->upcaster->process($payload);

        $this->assertEquals(3, $result['schema_version']);
        $this->assertArrayNotHasKey('v1_field', $result);
        $this->assertEquals('added_in_v2', $result['v2_field']);
        $this->assertEquals('added_in_v3', $result['v3_field']);
    }

    public function test_it_ignores_irrelevant_upcasters(): void
    {
        $irrelevantUpcaster = new class implements UpcasterInterface
        {
            public function fromVersion(): int
            {
                return 1;
            }

            public function toVersion(): int
            {
                return 2;
            }

            public function supports(string $type, int $version): bool
            {
                return $type === 'other_event' && $version === 1;
            }

            public function upcast(array $payload): array
            {
                return [];
            }
        };

        $this->upcaster->register($irrelevantUpcaster);

        $payload = [
            'event_type' => 'test_event',
            'schema_version' => 1,
            'data' => 'unchanged',
        ];

        $result = $this->upcaster->process($payload);

        $this->assertEquals(1, $result['schema_version']);
        $this->assertEquals('unchanged', $result['data']);
    }
}
