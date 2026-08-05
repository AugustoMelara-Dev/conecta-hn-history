<?php

declare(strict_types=1);

use App\Infrastructure\Observability\SentryEventScrubber;
use Sentry\Event;
use Sentry\UserDataBag;

test('sentry events remove request bodies credentials and user pii', function () {
    $event = Event::createEvent();
    $event->setRequest([
        'url' => 'https://example.test/ai/chat?email=private@example.test',
        'query_string' => 'email=private@example.test',
        'cookies' => ['session' => 'secret'],
        'headers' => [
            'authorization' => 'Bearer secret',
            'content-type' => 'application/json',
        ],
        'data' => ['prompt' => 'private business data'],
    ]);
    $event->setExtra([
        'api_key' => 'secret',
        'safe_metric' => 12,
    ]);
    $event->setUser(UserDataBag::createFromArray([
        'id' => 'user-id',
        'email' => 'private@example.test',
        'ip_address' => '127.0.0.1',
    ]));

    $scrubbed = SentryEventScrubber::scrub($event);

    expect($scrubbed->getRequest())
        ->not->toHaveKey('query_string')
        ->not->toHaveKey('cookies')
        ->and($scrubbed->getRequest()['url'])->toBe('https://example.test/ai/chat')
        ->and($scrubbed->getRequest()['data'])->toBe('[REDACTED]')
        ->and($scrubbed->getRequest()['headers']['authorization'])->toBe('[REDACTED]')
        ->and($scrubbed->getExtra()['api_key'])->toBe('[REDACTED]')
        ->and($scrubbed->getExtra()['safe_metric'])->toBe(12)
        ->and($scrubbed->getUser())->toBeNull();
});

test('sentry production configuration is privacy safe', function () {
    $config = require dirname(__DIR__, 3).'/config/sentry.php';

    expect($config['send_default_pii'])->toBeFalse()
        ->and($config['breadcrumbs']['sql_bindings'])->toBeFalse()
        ->and($config['tracing']['sql_bindings'])->toBeFalse()
        ->and($config['tracing']['gen_ai'])->toBeFalse()
        ->and($config['before_send'])->toBe([
            SentryEventScrubber::class,
            'scrub',
        ]);
});
