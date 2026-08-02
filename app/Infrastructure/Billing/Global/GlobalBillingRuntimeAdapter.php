<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Global;

use App\Application\Billing\Global\EntitlementServiceInterface;
use App\Application\Billing\Global\GlobalEntitlementService;
use App\Domain\Billing\Global\CreditLedgerInterface;
use App\Domain\Billing\Global\PriceBookReaderInterface;
use Illuminate\Contracts\Container\Container;

/**
 * Explicit Program 09 composition seam for supported global billing callers.
 *
 * Legacy billing and AI callers are not aliases of this boundary. They remain
 * outside the slice until a separately reviewed migration wires them here.
 */
final class GlobalBillingRuntimeAdapter
{
    public const LEGACY_ADOPTION_STATUS = 'not_migrated';

    public function __construct(
        private readonly Container $container,
    ) {
        $this->registerCanonicalBindings($container);
    }

    public function entitlementService(): EntitlementServiceInterface
    {
        return $this->container->make(EntitlementServiceInterface::class);
    }

    public function creditLedger(): CreditLedgerInterface
    {
        return $this->container->make(CreditLedgerInterface::class);
    }

    public function legacyAdoptionStatus(): string
    {
        return self::LEGACY_ADOPTION_STATUS;
    }

    private function registerCanonicalBindings(Container $container): void
    {
        if (config('dovra_billing.runtime.entitlement_authority') !== GlobalEntitlementService::class
            || config('dovra_billing.runtime.credit_ledger_authority') !== CreditLedgerInterface::class) {
            throw new \LogicException('Program 09 global billing canonical authorities are misconfigured.');
        }

        if (! $container->bound(PriceBookReaderInterface::class)) {
            $container->singleton(PriceBookReaderInterface::class, ConfigPriceBookReader::class);
        }

        if (! $container->bound(EntitlementServiceInterface::class)) {
            $container->singleton(EntitlementServiceInterface::class, GlobalEntitlementService::class);
        }

        if (! $container->bound(CreditLedgerInterface::class)) {
            $container->singleton(CreditLedgerInterface::class, ImmutableCreditLedger::class);
        }
    }
}
