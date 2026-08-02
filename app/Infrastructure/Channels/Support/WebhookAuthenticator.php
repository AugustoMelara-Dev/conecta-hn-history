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
            || ! self::present($message->webhookEventId)
            || ! self::present($message->webhookPayload)
            || ! self::present($message->webhookSignature)
            || ! self::present($message->webhookTimestamp)
            || ! self::present($message->webhookReplayKey)) {
            throw new InvalidArgumentException('Channel webhook authenticity is required.');
        }

        $expected = hash_hmac('sha256', $message->webhookPayload, $secret);
        if (! hash_equals($expected, $message->webhookSignature)) {
            throw new InvalidArgumentException('Channel webhook authenticity is invalid.');
        }
    }

    private static function present(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
