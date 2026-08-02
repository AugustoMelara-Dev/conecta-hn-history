<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ChannelContext
{
    /** @var array<string, mixed> */
    public array $marketCapabilities;

    /** @var array<string, mixed> */
    public array $connection;

    public readonly string $tenantId;

    public readonly string $channel;

    public readonly string $connectionKey;

    public readonly string $marketCountryCode;

    public readonly string $locale;

    public readonly string $currency;

    public readonly string $timezone;

    public readonly ?ChannelAuthorizationEnvelope $authorization;

    /**
     * @param  array<string, mixed>  $marketCapabilities
     * @param  array<string, mixed>  $connection
     */
    public function __construct(
        string $tenantId,
        string $channel,
        string $connectionKey,
        string $marketCountryCode,
        string $locale,
        string $currency,
        string $timezone,
        array $marketCapabilities = [],
        array $connection = [],
        ?ChannelAuthorizationEnvelope $authorization = null,
    ) {
        $tenantId = strtolower(trim($tenantId));
        $channel = strtolower(trim($channel));
        $connectionKey = trim($connectionKey);

        if (! Str::isUuid($tenantId) || $channel === '' || $connectionKey === '') {
            throw new InvalidArgumentException('Channel context is invalid.');
        }

        self::assertValidMarketContext($marketCountryCode, $locale, $currency, $timezone);

        if ($authorization === null
            || strtolower($authorization->tenantId) !== $tenantId) {
            throw new InvalidArgumentException('Channel context requires a matching actor authorization envelope.');
        }

        $this->tenantId = $tenantId;
        $this->channel = $channel;
        $this->connectionKey = $connectionKey;
        $this->marketCountryCode = strtoupper(trim($marketCountryCode));
        $this->locale = trim($locale);
        $this->currency = strtoupper(trim($currency));
        $this->timezone = trim($timezone);
        $this->marketCapabilities = $marketCapabilities;
        $this->connection = $connection;
        $this->authorization = $authorization;
    }

    public function supports(string $capability): bool
    {
        return data_get($this->marketCapabilities, $capability) === true;
    }

    public function providerKey(string $fallback): string
    {
        $configured = $this->connection['provider'] ?? null;

        return is_string($configured) && trim($configured) !== '' ? trim($configured) : $fallback;
    }

    public function actorId(): string
    {
        if ($this->authorization === null) {
            throw new InvalidArgumentException('Channel context has no actor authorization envelope.');
        }

        return $this->authorization->actorId;
    }

    public function assertInboundLineage(ChannelInboundMessage $message): void
    {
        if ($message->tenantId !== $this->tenantId
            || $message->channel !== $this->channel
            || $message->connectionKey !== $this->connectionKey) {
            throw new InvalidArgumentException('Inbound channel message is not bound to the supplied context.');
        }
    }

    public function assertOutboundLineage(ChannelOutboundMessage $message): void
    {
        if ($message->tenantId !== $this->tenantId
            || $message->channel !== $this->channel
            || $message->connectionKey !== $this->connectionKey) {
            throw new InvalidArgumentException('Outbound channel message is not bound to the supplied context.');
        }
    }

    public function assertNormalizedInboundLineage(NormalizedInboundMessage $message): void
    {
        if ($message->tenantId !== $this->tenantId
            || $message->channel !== $this->channel
            || $message->connectionKey !== $this->connectionKey) {
            throw new InvalidArgumentException('Normalized inbound message is not bound to the supplied context.');
        }
    }

    public function assertIdentityLineage(ResolvedChannelIdentity $identity): void
    {
        if ($identity->tenantId !== $this->tenantId
            || $identity->channel !== $this->channel) {
            throw new InvalidArgumentException('Resolved channel identity is not bound to the supplied context.');
        }
    }

    public static function assertValidMarketContext(
        string $marketCountryCode,
        string $locale,
        string $currency,
        string $timezone,
    ): void {
        $country = strtoupper(trim($marketCountryCode));
        $locale = trim($locale);
        $currency = strtoupper(trim($currency));
        $timezone = trim($timezone);

        if (! preg_match('/^[A-Z]{2}$/', $country)
            || in_array($country, ['ZZ', 'XX'], true)
            || ! preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/', $locale)
            || in_array(strtolower($locale), ['und', 'unknown'], true)
            || ! preg_match('/^[A-Z]{3}$/', $currency)
            || in_array($currency, ['XXX', 'UNK'], true)
            || in_array(strtolower($timezone), ['utc', 'etc/utc', 'gmt'], true)) {
            throw new InvalidArgumentException('Channel market context is missing or uses a placeholder value.');
        }

        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('Channel timezone is invalid.', previous: $exception);
        }
    }
}
