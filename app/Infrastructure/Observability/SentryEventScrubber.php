<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use App\Infrastructure\Logging\SensitiveDataScrubber;
use Sentry\Event;

final class SentryEventScrubber
{
    public static function scrub(Event $event): Event
    {
        $scrubber = new SensitiveDataScrubber;
        $request = $event->getRequest();

        if (array_key_exists('data', $request)) {
            $request['data'] = '[REDACTED]';
        }

        if (isset($request['url']) && is_string($request['url'])) {
            $request['url'] = preg_replace('/[?#].*$/', '', $request['url']) ?? $request['url'];
        }

        unset($request['cookies'], $request['query_string']);
        $event->setRequest($scrubber->scrub($request));
        $event->setExtra($scrubber->scrub($event->getExtra()));
        $event->setUser(null);

        foreach ($event->getContexts() as $name => $context) {
            $event->setContext((string) $name, $scrubber->scrub($context));
        }

        return $event;
    }
}
