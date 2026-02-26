<?php

declare(strict_types=1);

use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\CommandUpcasterInterface;
use App\Application\Shared\Decorators\UpcastingCommandBus;

it('upcasts commands and delegates to the next bus within tenant context', function () {
    $tenantId = 'tnt_' . fake()->bothify('??????????');

    $originalCommand = new class($tenantId) {
        public int $schemaVersion = 1;
        public function __construct(public string $tenantId) {}
    };

    $upcastedCommand = new class($tenantId) {
        public int $schemaVersion = 2;
        public function __construct(public string $tenantId) {}
    };

    $nextBus = Mockery::mock(CommandBusInterface::class);
    $nextBus->shouldReceive('dispatch')
        ->once()
        ->with($upcastedCommand)
        ->andReturn('success');

    $upcaster = Mockery::mock(CommandUpcasterInterface::class);
    $upcaster->shouldReceive('supports')
        ->with($originalCommand)
        ->andReturn(true);
    $upcaster->shouldReceive('upcast')
        ->with($originalCommand)
        ->andReturn($upcastedCommand);

    $decorator = new UpcastingCommandBus($nextBus, [$upcaster]);

    $result = $decorator->dispatch($originalCommand);

    expect($result)->toBe('success');
});

it('bypasses upcasting if not supported keeping tenant intact', function () {
    $command = new class('tnt_123') {
        public int $schemaVersion = 1;
        public function __construct(public string $tenantId) {}
    };

    $nextBus = Mockery::mock(CommandBusInterface::class);
    $nextBus->shouldReceive('dispatch')
        ->once()
        ->with($command)
        ->andReturn('passthrough');

    $upcaster = Mockery::mock(CommandUpcasterInterface::class);
    $upcaster->shouldReceive('supports')
        ->with($command)
        ->andReturn(false);
    $upcaster->shouldNotReceive('upcast');

    $decorator = new UpcastingCommandBus($nextBus, [$upcaster]);

    expect($decorator->dispatch($command))->toBe('passthrough');
});

it('chains multiple upcasters in sequence', function () {
    $tenantId = 'tnt_multi';

    $v1Command = new class($tenantId) {
        public int $schemaVersion = 1;
        public function __construct(public string $tenantId) {}
    };

    $v2Command = new class($tenantId) {
        public int $schemaVersion = 2;
        public function __construct(public string $tenantId) {}
    };

    $v3Command = new class($tenantId) {
        public int $schemaVersion = 3;
        public function __construct(public string $tenantId) {}
    };

    $nextBus = Mockery::mock(CommandBusInterface::class);
    $nextBus->shouldReceive('dispatch')
        ->once()
        ->with($v3Command)
        ->andReturn('v3_result');

    // First upcaster: v1 → v2
    $upcasterA = Mockery::mock(CommandUpcasterInterface::class);
    $upcasterA->shouldReceive('supports')->with($v1Command)->andReturn(true);
    $upcasterA->shouldReceive('upcast')->with($v1Command)->andReturn($v2Command);

    // Second upcaster: v2 → v3
    $upcasterB = Mockery::mock(CommandUpcasterInterface::class);
    $upcasterB->shouldReceive('supports')->with($v2Command)->andReturn(true);
    $upcasterB->shouldReceive('upcast')->with($v2Command)->andReturn($v3Command);

    $decorator = new UpcastingCommandBus($nextBus, [$upcasterA, $upcasterB]);

    expect($decorator->dispatch($v1Command))->toBe('v3_result');
});

it('passes command through unmodified when no upcasters are registered', function () {
    $command = new class('tnt_empty') {
        public int $schemaVersion = 1;
        public function __construct(public string $tenantId) {}
    };

    $nextBus = Mockery::mock(CommandBusInterface::class);
    $nextBus->shouldReceive('dispatch')
        ->once()
        ->with($command)
        ->andReturn('direct');

    $decorator = new UpcastingCommandBus($nextBus, []);

    expect($decorator->dispatch($command))->toBe('direct');
});
