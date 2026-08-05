<?php

declare(strict_types=1);

namespace App\Infrastructure\Channels\Support;

use App\Application\Channels\ChannelInboundMessage;
use App\Application\Channels\WebhookAuthenticityEnvelope;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
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

        if (preg_match('/\A[0-9a-fA-F]{64}\z/D', $message->webhookSignature) !== 1) {
            throw new InvalidArgumentException('Channel webhook authenticity is invalid.');
        }

        $verification = config('dovra_channels.contract.webhook_verification', []);
        if (! is_array($verification)
            || ($verification['algorithm'] ?? null) !== 'hmac-sha256'
            || ($verification['signature_version'] ?? null) !== WebhookAuthenticityEnvelope::VERSION
            || ($verification['timestamp_format'] ?? null) !== 'Y-m-d\\TH:i:s\\Z'
            || ! is_int($verification['max_age_seconds'] ?? null)
            || $verification['max_age_seconds'] < 1
            || ! is_int($verification['future_tolerance_seconds'] ?? null)
            || $verification['future_tolerance_seconds'] < 0) {
            throw new InvalidArgumentException('Channel webhook authenticity is invalid.');
        }

        try {
            $timestamp = CarbonImmutable::createFromFormat(
                '!Y-m-d\\TH:i:s\\Z',
                $message->webhookTimestamp,
                'UTC',
            );
        } catch (InvalidFormatException) {
            throw new InvalidArgumentException('Channel webhook authenticity is invalid.');
        }
        $errors = CarbonImmutable::getLastErrors();
        if (! $timestamp instanceof CarbonImmutable
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $timestamp->format('Y-m-d\\TH:i:s\\Z') !== $message->webhookTimestamp) {
            throw new InvalidArgumentException('Channel webhook authenticity is invalid.');
        }

        $now = CarbonImmutable::now('UTC')->startOfSecond();
        if ($timestamp->lt($now->subSeconds($verification['max_age_seconds']))
            || $timestamp->gt($now->addSeconds($verification['future_tolerance_seconds']))) {
            throw new InvalidArgumentException('Channel webhook authenticity is invalid.');
        }

        $envelope = WebhookAuthenticityEnvelope::fromInbound($message);
        $expected = hash_hmac('sha256', $envelope->canonicalBytes, $secret);
        if (! hash_equals($expected, $message->webhookSignature)) {
            throw new InvalidArgumentException('Channel webhook authenticity is invalid.');
        }
    }

    private static function present(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
