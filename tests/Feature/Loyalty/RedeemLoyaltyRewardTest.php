<?php

use App\Application\Loyalty\Commands\RedeemLoyaltyRewardCommand;
use App\Application\Loyalty\Contracts\LoyaltyRedemptionPortInterface;
use App\Application\Loyalty\Handlers\RedeemLoyaltyRewardHandler;
use App\Infrastructure\Loyalty\Adapters\LoyaltyRedemptionAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Explicit runtime binding ensures the test environment wires the adapter properly
    // without requiring blind AppServiceProvider mutations.
    app()->bind(LoyaltyRedemptionPortInterface::class, LoyaltyRedemptionAdapter::class);
});

test('handler throws exception when reward is unavailable', function () {
    $portMock = Mockery::mock(LoyaltyRedemptionPortInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getRewardCostIfAvailable')->once()->andReturn(null);
    });

    $handler = new RedeemLoyaltyRewardHandler($portMock);
    $command = new RedeemLoyaltyRewardCommand('tenant-1', 1, 999);

    expect(fn() => $handler->handle($command))
        ->toThrow(\DomainException::class, 'Reward is unavailable or does not exist.');
});

test('handler throws exception when balance is insufficient', function () {
    $portMock = Mockery::mock(LoyaltyRedemptionPortInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getRewardCostIfAvailable')->once()->andReturn(100);
        $mock->shouldReceive('getUserBalance')->once()->andReturn(50);
    });

    $handler = new RedeemLoyaltyRewardHandler($portMock);
    $command = new RedeemLoyaltyRewardCommand('tenant-1', 1, 999);

    expect(fn() => $handler->handle($command))
        ->toThrow(\DomainException::class, 'Insufficient loyalty points.');
});

test('handler processes redemption successfully', function () {
    $portMock = Mockery::mock(LoyaltyRedemptionPortInterface::class, function (MockInterface $mock) {
        $mock->shouldReceive('getRewardCostIfAvailable')->once()->andReturn(100);
        $mock->shouldReceive('getUserBalance')->once()->andReturn(150);
        $mock->shouldReceive('processRedemption')->once()->with(1, 'tenant-1', 999, 100);
    });

    $handler = new RedeemLoyaltyRewardHandler($portMock);
    $command = new RedeemLoyaltyRewardCommand('tenant-1', 1, 999);

    $handler->handle($command);

    // If no exception is thrown, the isolated Application logic executes correctly.
    expect(true)->toBeTrue();
});
