<?php

use App\Application\Sales\Commands\HoldCartInventoryCommand;
use App\Application\Sales\Contracts\InventoryHoldPortInterface;
use App\Application\Sales\Handlers\HoldCartInventoryHandler;
use App\Infrastructure\Sales\Adapters\EloquentInventoryHoldAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->bind(InventoryHoldPortInterface::class, EloquentInventoryHoldAdapter::class);
});

test('handler delegates hold creation to port correctly', function () {
    $portMock = Mockery::mock(InventoryHoldPortInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('createHolds')
            ->once()
            ->with('tenant-1', 'session-123', [['product_id' => 1, 'quantity' => 2]], 15);
    });

    $handler = new HoldCartInventoryHandler($portMock);
    $command = new HoldCartInventoryCommand('tenant-1', 'session-123', [['product_id' => 1, 'quantity' => 2]]);

    $handler->handle($command);

    expect(true)->toBeTrue();
});
