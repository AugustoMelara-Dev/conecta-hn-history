<?php

use App\Application\Sales\Commands\SyncCheckoutSessionCommand;
use App\Application\Sales\Contracts\CheckoutSessionPortInterface;
use App\Application\Sales\Handlers\SyncCheckoutSessionHandler;
use App\Infrastructure\Sales\Adapters\EloquentCheckoutSessionAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->bind(CheckoutSessionPortInterface::class, EloquentCheckoutSessionAdapter::class);
});

test('handler delegates checkout session sync to port correctly', function () {
    $portMock = Mockery::mock(CheckoutSessionPortInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('upsertSession')
            ->once()
            ->with('tenant-1', 'session-123', [['product_id' => 1, 'quantity' => 2]], 150.50, 99);
    });

    $handler = new SyncCheckoutSessionHandler($portMock);
    $command = new SyncCheckoutSessionCommand('tenant-1', 'session-123', [['product_id' => 1, 'quantity' => 2]], 150.50, 99);

    $handler->handle($command);

    expect(true)->toBeTrue();
});
