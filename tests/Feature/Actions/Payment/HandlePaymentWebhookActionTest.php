<?php

use App\Application\Billing\Actions\HandlePaymentWebhookAction;
use App\Application\Shared\Contracts\EventBusInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\Payment\PaymentWebhookHandlerInterface;
use App\Domain\Billing\Events\PaymentWebhookReceived;
use App\Domain\ValueObjects\Payment\PaymentWebhook;

test('action implements handler interface', function () {
    $bus = Mockery::mock(EventBusInterface::class);
    $logger = Mockery::mock(LoggerInterface::class);
    $action = new HandlePaymentWebhookAction($bus, $logger);
    expect($action)->toBeInstanceOf(PaymentWebhookHandlerInterface::class);
});

test('handle method dispatches event to ledger', function () {
    $webhook = PaymentWebhook::fromArray('payment.success', [
        'meta' => ['tenant_id' => 'tenant_123'],
        'id' => 'evt_123',
    ]);

    $bus = Mockery::mock(EventBusInterface::class);
    $bus->shouldReceive('dispatch')->once()->with(Mockery::type(PaymentWebhookReceived::class));
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->once();

    $action = new HandlePaymentWebhookAction($bus, $logger);
    $action->handle($webhook);
});
