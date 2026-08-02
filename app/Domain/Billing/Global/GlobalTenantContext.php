<?php

declare(strict_types=1);

namespace App\Domain\Billing\Global;

use App\Application\Markets\MarketContext;
use Carbon\CarbonImmutable;

final readonly class GlobalTenantContext
{
    public function __construct(
        private string $tenantId,
        private MarketContext $market,
        private string $plan,
        private string $status,
        private ?CarbonImmutable $graceEndsAt = null,
    ) {}

    /**
     * @param  array{
     *     tenant_id:string,
     *     market:MarketContext,
     *     plan:string,
     *     status:string,
     *     grace_ends_at?:string|null
     * }  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            tenantId: (string) $attributes['tenant_id'],
            market: $attributes['market'],
            plan: strtolower(trim((string) $attributes['plan'])),
            status: strtolower(trim((string) $attributes['status'])),
            graceEndsAt: isset($attributes['grace_ends_at']) && $attributes['grace_ends_at'] !== null
                ? CarbonImmutable::parse((string) $attributes['grace_ends_at'])
                : null,
        );
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function market(): MarketContext
    {
        return $this->market;
    }

    public function plan(): string
    {
        return $this->plan;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function graceEndsAt(): ?CarbonImmutable
    {
        return $this->graceEndsAt;
    }

    public function withPlan(string $plan): self
    {
        return new self(
            tenantId: $this->tenantId,
            market: $this->market,
            plan: strtolower(trim($plan)),
            status: $this->status,
            graceEndsAt: $this->graceEndsAt,
        );
    }
}
