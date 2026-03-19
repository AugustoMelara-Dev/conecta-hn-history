<?php

declare(strict_types=1);

use App\Application\Interactions\Commands\RecordInteractionCommand;
use App\Application\Interactions\DTOs\InteractionData;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\MetadataSanitizerInterface;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use App\Infrastructure\Interactions\Actions\RecordInteractionAction;

it('cleans metadata and dispatches a command within tenant context', function () {
    $commandBus = new InteractionRecordingCommandBus;
    $rateLimiter = new InteractionRecordingTenantRateLimiter;
    $sanitizer = new InteractionSanitizingMetadataStub(['clean' => 'data']);

    $action = new RecordInteractionAction($commandBus, $rateLimiter, $sanitizer);

    $data = new InteractionData(
        tenantId: 'tnt_1234567890',
        type: 'page_view',
        ipAddress: '127.0.0.1',
        userAgent: 'TestAgent/1.0',
        sessionId: 'sess_abc123',
        productId: null,
        metadata: ['dirty' => 'data'],
    );

    $action->execute($data);

    expect($rateLimiter->calls)->toHaveCount(1);
    expect($rateLimiter->calls[0])->toBe([
        'actionName' => RecordInteractionAction::class,
        'tenantId' => 'tnt_1234567890',
        'maxAttempts' => 1000,
        'decayMinutes' => 1,
    ]);

    expect($sanitizer->inputs)->toBe([
        ['dirty' => 'data'],
    ]);

    expect($commandBus->commands)->toHaveCount(1);
    expect($commandBus->commands[0])->toBeInstanceOf(RecordInteractionCommand::class);
    expect($commandBus->commands[0]->tenantId)->toBe('tnt_1234567890');
    expect($commandBus->commands[0]->visitorId)->toBe('sess_abc123');
    expect($commandBus->commands[0]->type)->toBe('page_view');
    expect($commandBus->commands[0]->metadata)->toBe(['clean' => 'data']);
});

it('throws RuntimeException when tenant context is missing', function () {
    $action = new RecordInteractionAction(
        new InteractionRecordingCommandBus,
        new InteractionRecordingTenantRateLimiter,
        new InteractionSanitizingMetadataStub([])
    );

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
    $commandBus = new InteractionRecordingCommandBus;
    $rateLimiter = new InteractionRecordingTenantRateLimiter;
    $sanitizer = new InteractionSanitizingMetadataStub([]);
    $action = new RecordInteractionAction($commandBus, $rateLimiter, $sanitizer);

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

final class InteractionRecordingCommandBus implements CommandBusInterface
{
    /** @var list<object> */
    public array $commands = [];

    public function dispatch(object $command): mixed
    {
        $this->commands[] = $command;

        return null;
    }
}

final class InteractionRecordingTenantRateLimiter implements TenantRateLimiterInterface
{
    /**
     * @var list<array{
     *     actionName: string,
     *     tenantId: string,
     *     maxAttempts: int,
     *     decayMinutes: int
     * }>
     */
    public array $calls = [];

    public function ensureNotExceeded(string $actionName, string $tenantId, int $maxAttempts = 100, int $decayMinutes = 1): void
    {
        $this->calls[] = [
            'actionName' => $actionName,
            'tenantId' => $tenantId,
            'maxAttempts' => $maxAttempts,
            'decayMinutes' => $decayMinutes,
        ];
    }
}

final class InteractionSanitizingMetadataStub implements MetadataSanitizerInterface
{
    /** @var list<array<mixed>> */
    public array $inputs = [];

    /** @param array<mixed> $result */
    public function __construct(private array $result) {}

    public function clean(array $metadata): array
    {
        $this->inputs[] = $metadata;

        return $this->result;
    }
}
