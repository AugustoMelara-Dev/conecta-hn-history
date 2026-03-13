<?php

declare(strict_types=1);

use App\Actions\Interactions\RecordInteractionAction;
use App\Application\Interactions\Commands\RecordInteractionCommand;
use App\Application\Interactions\DTOs\InteractionData;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\MetadataSanitizerInterface;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;

/**
 * RecordInteractionActionTest - CommandBus Delegation Verification
 *
 * Tests the Action as a pure Infrastructure Adapter:
 * DTO -> Command mapping -> Bus dispatch.
 * No Repositories, no Models, no database.
 */

test('record interaction action dispatches command via bus', function () {
    $dto = new InteractionData(
        tenantId: 'tenant-uuid-001',
        type: 'whatsapp_click',
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent/1.0',
        sessionId: 'session-abc-123',
        productId: '456',
        metadata: ['source' => 'web'],
    );

    $busMock = Mockery::mock(CommandBusInterface::class);
    $busMock->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(function (RecordInteractionCommand $command) use ($dto) {
            return $command->tenantId === $dto->tenantId
                && $command->visitorId === $dto->sessionId
                && $command->type === $dto->type
                && $command->productId === $dto->productId
                && $command->ipAddress === $dto->ipAddress
                && $command->userAgent === $dto->userAgent
                && $command->metadata === $dto->metadata;
        }));

    $rateLimiterMock = Mockery::mock(TenantRateLimiterInterface::class);
    $rateLimiterMock->shouldReceive('ensureNotExceeded')->once();
    $sanitizerMock = Mockery::mock(MetadataSanitizerInterface::class);
    $sanitizerMock->shouldReceive('clean')->once()->andReturn(['source' => 'web']);

    $action = new RecordInteractionAction($busMock, $rateLimiterMock, $sanitizerMock);
    $action->execute($dto);
});

test('record interaction action rejects missing tenant context', function () {
    $dto = new InteractionData(
        tenantId: '',
        type: 'product_view',
        ipAddress: '10.0.0.1',
        userAgent: 'Bot/2.0',
        sessionId: 'session-xyz',
    );

    $busMock = Mockery::mock(CommandBusInterface::class);
    $busMock->shouldNotReceive('dispatch');
    $rateLimiterMock = Mockery::mock(TenantRateLimiterInterface::class);
    $sanitizerMock = Mockery::mock(MetadataSanitizerInterface::class);

    $action = new RecordInteractionAction($busMock, $rateLimiterMock, $sanitizerMock);
    $action->execute($dto);
})->throws(\RuntimeException::class, 'Cannot record interaction: missing tenant context.');

test('record interaction action rejects oversized metadata', function () {
    $oversizedMetadata = array_fill(0, 500, str_repeat('x', 100));

    $dto = new InteractionData(
        tenantId: 'tenant-uuid-002',
        type: 'product_view',
        ipAddress: '10.0.0.1',
        userAgent: 'Bot/2.0',
        sessionId: 'session-xyz',
        metadata: $oversizedMetadata,
    );

    $busMock = Mockery::mock(CommandBusInterface::class);
    $busMock->shouldNotReceive('dispatch');

    $rateLimiterMock = Mockery::mock(TenantRateLimiterInterface::class);
    $rateLimiterMock->shouldReceive('ensureNotExceeded')->once();
    $sanitizerMock = Mockery::mock(MetadataSanitizerInterface::class);

    $action = new RecordInteractionAction($busMock, $rateLimiterMock, $sanitizerMock);
    $action->execute($dto);
})->throws(\InvalidArgumentException::class, "Interaction 'metadata' payload exceeds 10240 bytes.");

test('record interaction action maps null optional fields correctly', function () {
    $dto = new InteractionData(
        tenantId: 'tenant-uuid-003',
        type: 'page_view',
        ipAddress: null,
        userAgent: null,
        sessionId: null,
    );

    $busMock = Mockery::mock(CommandBusInterface::class);
    $busMock->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(function (RecordInteractionCommand $command) {
            return $command->tenantId === 'tenant-uuid-003'
                && $command->visitorId === ''
                && $command->type === 'page_view'
                && $command->productId === null
                && $command->ipAddress === null
                && $command->userAgent === null
                && $command->metadata === [];
        }));

    $rateLimiterMock = Mockery::mock(TenantRateLimiterInterface::class);
    $rateLimiterMock->shouldReceive('ensureNotExceeded')->once();
    $sanitizerMock = Mockery::mock(MetadataSanitizerInterface::class);
    $sanitizerMock->shouldReceive('clean')->once()->andReturn([]);

    $action = new RecordInteractionAction($busMock, $rateLimiterMock, $sanitizerMock);
    $action->execute($dto);
});

test('RecordInteractionCommand complies with the v1 JSON schema contract', function () {
    $command = new RecordInteractionCommand(
        tenantId: 'tenant-uuid-schema-check-000000000001',
        visitorId: 'visitor-001',
        type: 'schema_validation',
        metadata: ['valid' => true]
    );

    $serialized = json_decode(json_encode($command), true);
    $schema = json_decode(file_get_contents('tests/Schemas/Commands/RecordInteractionCommand.v1.json'), true);

    expect($serialized)->toBeArray();
    expect(array_keys($serialized))->toEqualCanonicalizing(array_keys($schema['properties']));

    foreach ($schema['required'] as $requiredKey) {
        expect(array_key_exists($requiredKey, $serialized))->toBeTrue();
    }

    expect($serialized['tenantId'])->toBeString();
    expect(strlen($serialized['tenantId']))->toBeGreaterThanOrEqual(36);
    expect($serialized['visitorId'])->toBeString();
    expect($serialized['type'])->toBeString();
    expect($serialized['metadata'])->toBeArray();
    expect($serialized['productId'])->toBeNull();
    expect($serialized['ipAddress'])->toBeNull();
    expect($serialized['userAgent'])->toBeNull();
    expect($serialized['referrer'])->toBeNull();
    expect($serialized['url'])->toBeNull();
    expect($serialized['idempotencyKey'])->toBeNull();
    expect($serialized['schemaVersion'])->toBeInt();
});
