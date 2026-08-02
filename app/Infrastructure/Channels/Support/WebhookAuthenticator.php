<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Support;

use App\Application\Channels\ChannelInboundMessage;
use InvalidArgumentException;

final class WebhookAuthenticator
{
    public static function assertSigned(ChannelInboundMessage $message, ?string $secret): void
    {
        if (! is_string($secret)
            || trim($secret) === ''
            || $message->webhookEventId === null
            || $message->webhookPayload === null
            || $message->webhookSignature === null) {
            throw new InvalidArgumentException('Channel webhook authenticity is required.');
        }

        $expected = hash_hmac('sha256', $message->webhookPayload, $secret);
        if (! hash_equals($expected, $message->webhookSignature)) {
            throw new InvalidArgumentException('Channel webhook authenticity is invalid.');
        }
    }
}
