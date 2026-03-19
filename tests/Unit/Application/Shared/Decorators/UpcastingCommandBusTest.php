<?php

declare(strict_types=1);

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\CommandUpcasterInterface;
use App\Application\Shared\Decorators\UpcastingCommandBus;

final class RecordingCommandBus implements CommandBusInterface
{
    public ?object $receivedCommand = null;

    public function __construct(private mixed $result) {}

    public function dispatch(object $command): mixed
    {
        $this->receivedCommand = $command;

        return $this->result;
    }
}

final class CallbackUpcaster implements CommandUpcasterInterface
{
    /**
     * @param  callable(object): bool  $supports
     * @param  callable(object): object  $upcast
     */
    public function __construct(
        private $supports,
        private $upcast,
    ) {}

    public function supports(object $command): bool
    {
        return ($this->supports)($command);
    }

    public function upcast(object $command): object
    {
        return ($this->upcast)($command);
    }
}

it('upcasts commands and delegates to the next bus within tenant context', function () {
    $tenantId = 'tnt_'.fake()->bothify('??????????');

    $originalCommand = new class($tenantId)
    {
        public int $schemaVersion = 1;

        public function __construct(public string $tenantId) {}
    };

    $upcastedCommand = new class($tenantId)
    {
        public int $schemaVersion = 2;

        public function __construct(public string $tenantId) {}
    };

    $nextBus = new RecordingCommandBus('success');
    $upcaster = new CallbackUpcaster(
        supports: fn (object $command): bool => $command === $originalCommand,
        upcast: fn (object $command): object => $command === $originalCommand ? $upcastedCommand : $command,
    );

    $decorator = new UpcastingCommandBus($nextBus, [$upcaster]);

    $result = $decorator->dispatch($originalCommand);

    expect($result)->toBe('success');
    expect($nextBus->receivedCommand)->toBe($upcastedCommand);
});

it('bypasses upcasting if not supported keeping tenant intact', function () {
    $command = new class('tnt_123')
    {
        public int $schemaVersion = 1;

        public function __construct(public string $tenantId) {}
    };

    $nextBus = new RecordingCommandBus('passthrough');
    $upcaster = new CallbackUpcaster(
        supports: fn (object $received): bool => false,
        upcast: fn (object $received): object => $received,
    );

    $decorator = new UpcastingCommandBus($nextBus, [$upcaster]);

    expect($decorator->dispatch($command))->toBe('passthrough');
    expect($nextBus->receivedCommand)->toBe($command);
});

it('chains multiple upcasters in sequence', function () {
    $tenantId = 'tnt_multi';

    $v1Command = new class($tenantId)
    {
        public int $schemaVersion = 1;

        public function __construct(public string $tenantId) {}
    };

    $v2Command = new class($tenantId)
    {
        public int $schemaVersion = 2;

        public function __construct(public string $tenantId) {}
    };

    $v3Command = new class($tenantId)
    {
        public int $schemaVersion = 3;

        public function __construct(public string $tenantId) {}
    };

    $nextBus = new RecordingCommandBus('v3_result');
    $upcasterA = new CallbackUpcaster(
        supports: fn (object $command): bool => $command === $v1Command,
        upcast: fn (object $command): object => $command === $v1Command ? $v2Command : $command,
    );
    $upcasterB = new CallbackUpcaster(
        supports: fn (object $command): bool => $command === $v2Command,
        upcast: fn (object $command): object => $command === $v2Command ? $v3Command : $command,
    );

    $decorator = new UpcastingCommandBus($nextBus, [$upcasterA, $upcasterB]);

    expect($decorator->dispatch($v1Command))->toBe('v3_result');
    expect($nextBus->receivedCommand)->toBe($v3Command);
});

it('passes command through unmodified when no upcasters are registered', function () {
    $command = new class('tnt_empty')
    {
        public int $schemaVersion = 1;

        public function __construct(public string $tenantId) {}
    };

    $nextBus = new RecordingCommandBus('direct');
    $decorator = new UpcastingCommandBus($nextBus, []);

    expect($decorator->dispatch($command))->toBe('direct');
    expect($nextBus->receivedCommand)->toBe($command);
});
