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

    public function key(): string
    {
        return $this->key;
    }

    public function requiresLiveEntitlement(): bool
    {
        return $this->requiresLiveEntitlement;
    }
}
