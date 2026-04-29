<?php

declare(strict_types=1);

$applicationNamespace = 'App\Application';

test('application layer does not use eloquent models', function () use ($applicationNamespace) {
    expect($applicationNamespace)
        ->not()->toUse('Illuminate\Database\Eloquent\Model')
        ->not()->toExtend('Illuminate\Database\Eloquent\Model');
});

test('application layer is completely decoupled from http delivery mechanisms', function () use ($applicationNamespace) {
    expect($applicationNamespace)
        ->not()->toUse([
            'Illuminate\Http\Request',
            'Illuminate\Support\Facades\Session',
            'Illuminate\Support\Facades\Cookie',
        ]);
});

test('application layer does not use controllers', function () use ($applicationNamespace) {
    expect($applicationNamespace)
        ->not()->toUse('App\Http\Controllers');
});

test('application layer does not construct explicit sql queries or use raw DB facade', function () use ($applicationNamespace) {
    expect($applicationNamespace)
        ->not()->toUse([
            'Illuminate\Support\Facades\DB',
            'Illuminate\Database\Connection',
            'Illuminate\Database\ConnectionInterface',
        ])
        ->ignoring([
            // Queue dispatch uses read-only audience projections until those readers move behind ports.
            'App\Application\Marketing\Jobs\DispatchCampaignSegment',
            // Refunds still require a provider call inside a locked payment transaction.
            'App\Application\Billing\Services\RefundSubscriptionPaymentService',
            // Webhook replay and subscription-intent reconciliation are still DB-constraint driven.
            'App\Application\Billing\Listeners\ProcessPaymentWebhookReceived',
        ]);
});
