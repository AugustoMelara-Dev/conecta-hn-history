<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

final readonly class BillingCapability
{
    public function __construct(
        private string $key,
        private bool $requiresLiveEntitlement,
    ) {}

    public static function sandboxAgent(): self
    {
        return new self('ai.sandbox_agent', false);
    }

    public static function liveAgent(): self
    {
        return new self('ai.live_agent', true);
    }

    public static function billingPortal(): self
    {
        return new self('billing.portal', false);
    }

    public static function voice(): self
    {
        return new self('voice', true);
    }

    public static function api(): self
    {
        return new self('api', true);
    }

    public static function prioritySupport(): self
    {
        return new self('support.priority', false);
    }

    public static function fromKey(string $key): self
    {
        $normalized = strtolower(trim($key));

        return match ($normalized) {
            'ai.sandbox_agent', 'workforce.sandbox' => self::sandboxAgent(),
            'ai.live_agent', 'workforce.live', 'workforce.agent' => self::liveAgent(),
            'billing.portal' => self::billingPortal(),
            'voice', 'workforce.voice' => self::voice(),
            'api', 'workforce.api' => self::api(),
            'support.priority', 'workforce.priority_support' => self::prioritySupport(),
            default => new self($normalized, self::isLiveKey($normalized)),
        };
    }

    public function key(): string
    {
        return $this->key;
    }

    public function requiresLiveEntitlement(): bool
    {
        return $this->requiresLiveEntitlement;
    }

    public function isLiveOnly(): bool
    {
        return $this->requiresLiveEntitlement;
    }

    private static function isLiveKey(string $key): bool
    {
        return ! in_array($key, ['billing.portal', 'ai.sandbox_agent', 'workforce.sandbox'], true);
    }
}
