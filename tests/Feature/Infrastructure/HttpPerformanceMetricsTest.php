<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;

uses(DatabaseTransactions::class);

test('http performance middleware emits structured metric when enabled', function () {
    config()->set('performance.http.enabled', true);
    config()->set('performance.http.sample_rate', 1.0);
    config()->set('performance.http.query_metrics_enabled', false);

    Log::spy();

    $this->getJson('/api/v1/directory/search?limit=1')->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'http.request.completed'
                && ($context['event'] ?? null) === 'http.request.completed'
                && isset($context['duration_ms'], $context['route_uri'], $context['surface']);
        })
        ->once();
});
