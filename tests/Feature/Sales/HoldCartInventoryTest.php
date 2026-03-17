<?php

use App\Application\Sales\Commands\HoldCartInventoryCommand;
use App\Application\Inventory\Contracts\InventoryHoldPortInterface;
use App\Application\Sales\Handlers\HoldCartInventoryHandler;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use Mockery\MockInterface;

test('handler delegates hold creation to port correctly', function () {
    $portMock = Mockery::mock(InventoryHoldPortInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('reserve')
            ->once()
            ->with('tenant-1', 'location-1', 'session-123', 1, 2.0, 15);
    });

    $transactionManager = Mockery::mock(TransactionManagerInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn (callable $callback) => $callback());
    });

    $handler = new HoldCartInventoryHandler($portMock, $transactionManager);
    $command = new HoldCartInventoryCommand('tenant-1', 'location-1', 'session-123', [['product_id' => 1, 'quantity' => 2]]);

    $handler->handle($command);

    expect(true)->toBeTrue();
});
