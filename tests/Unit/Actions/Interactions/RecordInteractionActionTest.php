<?php

declare(strict_types=1);

use App\Actions\Interactions\RecordInteractionAction;
use App\Application\Interactions\DTOs\InteractionData;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\MetadataSanitizerInterface;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;

it('cleans metadata and dispatches command within tenant context', function () {
    // Scaffold Tenant Context
    $tenantId = 'tnt_' . fake()->bothify('??????????');

    // Mock Dependencies
    $commandBus = Mockery::mock(CommandBusInterface::class);
    $commandBus->shouldReceive('dispatch')->once();

    $rateLimiter = Mockery::mock(TenantRateLimiterInterface::class);
    $rateLimiter->shouldReceive('ensureNotExceeded')->once();

    $sanitizer = Mockery::mock(MetadataSanitizerInterface::class);
    $sanitizer->shouldReceive('clean')
        ->once()
        ->with(['dirty' => 'data'])
        ->andReturn(['clean' => 'data']);

    // Instantiate Action with DI
    $action = new RecordInteractionAction($commandBus, $rateLimiter, $sanitizer);

    // Create properly typed DTO
    $data = new InteractionData(
        tenantId: $tenantId,
        type: 'page_view',
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent/1.0',
        sessionId: 'sess_abc123',
        productId: null,
        metadata: ['dirty' => 'data'],
    );

    // Execute — should call sanitizer->clean() and commandBus->dispatch()
    $action->execute($data);
});

it('throws RuntimeException when tenant context is missing', function () {
    $commandBus = Mockery::mock(CommandBusInterface::class);
    $rateLimiter = Mockery::mock(TenantRateLimiterInterface::class);
    $sanitizer = Mockery::mock(MetadataSanitizerInterface::class);

    $action = new RecordInteractionAction($commandBus, $rateLimiter, $sanitizer);

    $data = new InteractionData(
        tenantId: '',
        type: 'page_view',
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent/1.0',
        sessionId: 'sess_abc123',
    );

    $action->execute($data);
})->throws(RuntimeException::class, 'Cannot record interaction: missing tenant context.');

it('throws InvalidArgumentException when metadata exceeds size limit', function () {
    $commandBus = Mockery::mock(CommandBusInterface::class);
    $rateLimiter = Mockery::mock(TenantRateLimiterInterface::class);
    $rateLimiter->shouldReceive('ensureNotExceeded')->once();
    $sanitizer = Mockery::mock(MetadataSanitizerInterface::class);

    $action = new RecordInteractionAction($commandBus, $rateLimiter, $sanitizer);

    // Create oversized metadata (>10KB)
    $oversizedMetadata = ['payload' => str_repeat('x', 11000)];

    $data = new InteractionData(
        tenantId: 'tnt_test123',
        type: 'page_view',
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent/1.0',
        sessionId: 'sess_abc123',
        metadata: $oversizedMetadata,
    );

    $action->execute($data);
})->throws(InvalidArgumentException::class, 'The metadata exceeds the maximum allowed size of 10KB.');
