<?php

declare(strict_types=1);

use App\Actions\Interactions\RecordInteractionAction;
use App\Application\Interactions\Commands\RecordInteractionCommand;
use App\Application\Interactions\DTOs\InteractionData;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;

/**
 * RecordInteractionActionTest - CommandBus Delegation Verification
 *
 * Tests the Action as a pure Infrastructure Adapter:
 * DTO → Command mapping → Bus dispatch.
 * No Repositories, no Models, no database.
 */

test('record interaction action dispatches command via bus', function () {
    // Arrange: Input DTO
    $dto = new InteractionData(
        tenantId: 'tenant-uuid-001',
        type: 'whatsapp_click',
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent/1.0',
        sessionId: 'session-abc-123',
        productId: '456',
        metadata: ['source' => 'web'],
    );

    // Arrange: Mock Bus — expect exactly one dispatch with correct Command payload
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

    // Act
    $rateLimiterMock = Mockery::mock(TenantRateLimiterInterface::class);
    $rateLimiterMock->shouldReceive('ensureNotExceeded')->once();

    $action = new RecordInteractionAction($busMock, $rateLimiterMock);
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

    $action = new RecordInteractionAction($busMock, $rateLimiterMock);
    $action->execute($dto);
})->throws(\RuntimeException::class, 'missing tenant context');

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

    $action = new RecordInteractionAction($busMock, $rateLimiterMock);
    $action->execute($dto);
})->throws(\InvalidArgumentException::class, 'maximum allowed size');

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
                && $command->visitorId === ''  // sessionId null → empty string
                && $command->type === 'page_view'
                && $command->productId === null
                && $command->ipAddress === null
                && $command->userAgent === null
                && $command->metadata === [];
        }));

    $rateLimiterMock = Mockery::mock(TenantRateLimiterInterface::class);
    $rateLimiterMock->shouldReceive('ensureNotExceeded')->once();

    $action = new RecordInteractionAction($busMock, $rateLimiterMock);
    $action->execute($dto);
});

test('RecordInteractionCommand complies with the v1 JSON schema contract', function () {
    $command = new RecordInteractionCommand(
        tenantId: 'tenant-uuid-schema-check',
        visitorId: 'visitor-001',
        type: 'schema_validation',
        metadata: ['valid' => true]
    );

    // json_encode -> json_decode to simulate serialization and return object for validation
    // The Validator wants an object (stdClass) or array
    $serialized = json_decode(json_encode($command));

    expect($serialized)->toMatchJsonSchema('tests/Schemas/Commands/RecordInteractionCommand.v1.json');
});
