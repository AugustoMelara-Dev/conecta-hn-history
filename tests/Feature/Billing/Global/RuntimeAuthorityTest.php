<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Global;

use App\Application\Billing\Global\EntitlementServiceInterface;
use App\Application\Billing\Global\GlobalEntitlementService;
use App\Domain\Billing\Global\CreditLedgerInterface;
use App\Infrastructure\Billing\Global\GlobalBillingRuntimeAdapter;
use App\Infrastructure\Billing\Global\ImmutableCreditLedger;
use Tests\TestCase;

final class RuntimeAuthorityTest extends TestCase
{
    public function test_configured_global_billing_adapter_wires_canonical_authorities_into_the_container(): void
    {
        $adapterClass = (string) config('dovra_billing.runtime.adapter');

        self::assertSame(GlobalBillingRuntimeAdapter::class, $adapterClass);

        /** @var GlobalBillingRuntimeAdapter $runtime */
        $runtime = app()->make($adapterClass);

        self::assertInstanceOf(GlobalEntitlementService::class, $runtime->entitlementService());
        self::assertInstanceOf(ImmutableCreditLedger::class, $runtime->creditLedger());
        self::assertInstanceOf(GlobalEntitlementService::class, app()->make(EntitlementServiceInterface::class));
        self::assertInstanceOf(ImmutableCreditLedger::class, app()->make(CreditLedgerInterface::class));
        self::assertSame('not_migrated', $runtime->legacyAdoptionStatus());
    }
}
