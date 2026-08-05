<?php

declare(strict_types=1);

it('keeps legacy order command handlers as canonical writer adapters', function (): void {
    $app = dirname(__DIR__, 2).'/app';
    $createHandler = file_get_contents($app.'/Application/Sales/Handlers/CreateOrderHandler.php');
    $cancelHandler = file_get_contents($app.'/Application/Sales/Handlers/CancelOrderHandler.php');

    expect($createHandler)
        ->toContain('CanonicalOrderWriter')
        ->not->toContain('OrderRepositoryInterface')
        ->not->toContain('InventoryManager')
        ->not->toContain('PriceCalculatorService')
        ->not->toContain('CouponValidatorService')
        ->and($cancelHandler)
        ->toContain('CanonicalOrderWriter')
        ->not->toContain('OrderRepositoryInterface')
        ->not->toContain('TransactionalEventDispatcher');
});

it('keeps the forensic admin order resource read only and market aware', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Resources/OrderResource.php');
    $merchantResource = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/App/Resources/OrderResource.php',
    );

    expect($resource)
        ->not->toContain('OrderStateService')
        ->not->toContain("Action::make('complete')")
        ->not->toContain("money('HNL')")
        ->and($merchantResource)
        ->not->toContain("money('HNL')");
});

it('routes governance cancellations through the canonical order writer', function (): void {
    $job = file_get_contents(
        dirname(__DIR__, 2).'/app/Infrastructure/Jobs/Governance/ReleaseStaleOrders.php',
    );

    expect($job)
        ->toContain('CanonicalOrderWriter')
        ->toContain('systemInitiated: true')
        ->not->toContain('OrderStateService');
});
