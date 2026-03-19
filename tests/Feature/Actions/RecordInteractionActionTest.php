<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Interactions\RecordInteractionAction;
use App\Application\Interactions\Commands\RecordInteractionCommand;
use App\Application\Interactions\DTOs\InteractionData;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\MetadataSanitizerInterface;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class RecordInteractionActionTest extends TestCase
{
    #[Test]
    public function record_interaction_action_dispatches_command_via_bus(): void
    {
        $dto = new InteractionData(
            tenantId: 'tenant-uuid-001',
            type: 'whatsapp_click',
            ipAddress: '127.0.0.1',
            userAgent: 'TestAgent/1.0',
            sessionId: 'session-abc-123',
            productId: '456',
            metadata: ['source' => 'web'],
        );

        $commandBus = new RecordingCommandBus;
        $rateLimiter = new RecordingTenantRateLimiter;
        $sanitizer = new StubMetadataSanitizer(['source' => 'web']);

        $action = new RecordInteractionAction($commandBus, $rateLimiter, $sanitizer);
        $action->execute($dto);

        self::assertSame(1, $rateLimiter->calls);
        self::assertSame(1, $sanitizer->calls);
        self::assertInstanceOf(RecordInteractionCommand::class, $commandBus->lastCommand);

        $command = $commandBus->lastCommand;
        self::assertSame($dto->tenantId, $command->tenantId);
        self::assertSame($dto->sessionId, $command->visitorId);
        self::assertSame($dto->type, $command->type);
        self::assertSame($dto->productId, $command->productId);
        self::assertSame($dto->ipAddress, $command->ipAddress);
        self::assertSame($dto->userAgent, $command->userAgent);
        self::assertSame($dto->metadata, $command->metadata);
    }

    #[Test]
    public function record_interaction_action_rejects_missing_tenant_context(): void
    {
        $dto = new InteractionData(
            tenantId: '',
            type: 'product_view',
            ipAddress: '10.0.0.1',
            userAgent: 'Bot/2.0',
            sessionId: 'session-xyz',
        );

        $commandBus = new RecordingCommandBus;
        $rateLimiter = new RecordingTenantRateLimiter;
        $sanitizer = new StubMetadataSanitizer([]);

        $action = new RecordInteractionAction($commandBus, $rateLimiter, $sanitizer);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot record interaction: missing tenant context.');

        try {
            $action->execute($dto);
        } finally {
            self::assertSame(0, $rateLimiter->calls);
            self::assertSame(0, $commandBus->dispatchCount);
            self::assertSame(0, $sanitizer->calls);
        }
    }

    #[Test]
    public function record_interaction_action_rejects_oversized_metadata(): void
    {
        /** @var array<int, string> $oversizedMetadata */
        $oversizedMetadata = array_fill(0, 500, str_repeat('x', 100));

        $dto = new InteractionData(
            tenantId: 'tenant-uuid-002',
            type: 'product_view',
            ipAddress: '10.0.0.1',
            userAgent: 'Bot/2.0',
            sessionId: 'session-xyz',
            metadata: $oversizedMetadata,
        );

        $commandBus = new RecordingCommandBus;
        $rateLimiter = new RecordingTenantRateLimiter;
        $sanitizer = new StubMetadataSanitizer([]);

        $action = new RecordInteractionAction($commandBus, $rateLimiter, $sanitizer);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Interaction 'metadata' payload exceeds 10240 bytes.");

        try {
            $action->execute($dto);
        } finally {
            self::assertSame(1, $rateLimiter->calls);
            self::assertSame(0, $sanitizer->calls);
            self::assertSame(0, $commandBus->dispatchCount);
        }
    }

    #[Test]
    public function record_interaction_action_maps_null_optional_fields_correctly(): void
    {
        $dto = new InteractionData(
            tenantId: 'tenant-uuid-003',
            type: 'page_view',
            ipAddress: null,
            userAgent: null,
            sessionId: null,
        );

        $commandBus = new RecordingCommandBus;
        $rateLimiter = new RecordingTenantRateLimiter;
        $sanitizer = new StubMetadataSanitizer([]);

        $action = new RecordInteractionAction($commandBus, $rateLimiter, $sanitizer);
        $action->execute($dto);

        self::assertInstanceOf(RecordInteractionCommand::class, $commandBus->lastCommand);

        $command = $commandBus->lastCommand;
        self::assertSame('tenant-uuid-003', $command->tenantId);
        self::assertSame('', $command->visitorId);
        self::assertSame('page_view', $command->type);
        self::assertNull($command->productId);
        self::assertNull($command->ipAddress);
        self::assertNull($command->userAgent);
        self::assertSame([], $command->metadata);
    }

    #[Test]
    public function record_interaction_command_complies_with_the_v1_json_schema_contract(): void
    {
        $command = new RecordInteractionCommand(
            tenantId: 'tenant-uuid-schema-check-000000000001',
            visitorId: 'visitor-001',
            type: 'schema_validation',
            metadata: ['valid' => true],
        );

        $serializedJson = json_encode($command, JSON_THROW_ON_ERROR);
        $serialized = json_decode($serializedJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($serialized);

        $schemaJson = file_get_contents(base_path('tests/Schemas/Commands/RecordInteractionCommand.v1.json'));
        self::assertIsString($schemaJson);

        $schema = json_decode($schemaJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($schema);

        /** @var array{properties: array<string, mixed>, required: list<string>} $schema */
        /** @var array<string, mixed> $serialized */

        self::assertEqualsCanonicalizing(array_keys($schema['properties']), array_keys($serialized));

        foreach ($schema['required'] as $requiredKey) {
            self::assertArrayHasKey($requiredKey, $serialized);
        }

        self::assertIsString($serialized['tenantId']);
        self::assertGreaterThanOrEqual(36, strlen((string) $serialized['tenantId']));
        self::assertIsString($serialized['visitorId']);
        self::assertIsString($serialized['type']);
        self::assertIsArray($serialized['metadata']);
        self::assertNull($serialized['productId']);
        self::assertNull($serialized['ipAddress']);
        self::assertNull($serialized['userAgent']);
        self::assertNull($serialized['referrer']);
        self::assertNull($serialized['url']);
        self::assertNull($serialized['idempotencyKey']);
        self::assertIsInt($serialized['schemaVersion']);
    }
}

final class RecordingCommandBus implements CommandBusInterface
{
    public ?RecordInteractionCommand $lastCommand = null;

    public int $dispatchCount = 0;

    public function dispatch(object $command): mixed
    {
        if ($command instanceof RecordInteractionCommand) {
            $this->lastCommand = $command;
        }

        $this->dispatchCount++;

        return null;
    }
}

final class RecordingTenantRateLimiter implements TenantRateLimiterInterface
{
    public int $calls = 0;

    public ?string $lastActionName = null;

    public ?string $lastTenantId = null;

    public ?int $lastMaxAttempts = null;

    public ?int $lastDecayMinutes = null;

    public function ensureNotExceeded(string $actionName, string $tenantId, int $maxAttempts = 100, int $decayMinutes = 1): void
    {
        $this->calls++;
        $this->lastActionName = $actionName;
        $this->lastTenantId = $tenantId;
        $this->lastMaxAttempts = $maxAttempts;
        $this->lastDecayMinutes = $decayMinutes;
    }
}

final class StubMetadataSanitizer implements MetadataSanitizerInterface
{
    /** @var array<mixed> */
    private array $cleanedMetadata;

    public int $calls = 0;

    /** @var array<mixed>|null */
    public ?array $receivedMetadata = null;

    /**
     * @param  array<mixed>  $cleanedMetadata
     */
    public function __construct(array $cleanedMetadata)
    {
        $this->cleanedMetadata = $cleanedMetadata;
    }

    public function clean(array $metadata): array
    {
        $this->calls++;
        $this->receivedMetadata = $metadata;

        return $this->cleanedMetadata;
    }
}
