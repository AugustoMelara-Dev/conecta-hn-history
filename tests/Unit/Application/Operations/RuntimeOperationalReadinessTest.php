<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Operations;

use App\Application\Operations\RuntimeOperationalReadiness;
use Tests\TestCase;

final class RuntimeOperationalReadinessTest extends TestCase
{
    public function test_internal_policy_can_pass_while_external_providers_remain_explicitly_blocked(): void
    {
        $report = app(RuntimeOperationalReadiness::class)->inspect();

        self::assertTrue($report['internal_ready']);
        self::assertSame('external_blocked', $report['status']);
        self::assertNotEmpty($report['external_pending']);
        self::assertContains('external_blocked', array_column($report['providers'], 'status'));
        self::assertContains('local_only', array_column($report['providers'], 'status'));
    }

    public function test_invalid_market_currency_fails_internal_readiness_without_hnl_fallback(): void
    {
        config()->set('dovra_billing.price_books.us', ['version' => 'v1', 'currency' => '', 'plans' => []]);

        $report = app(RuntimeOperationalReadiness::class)->inspect();

        self::assertFalse($report['internal_ready']);
        self::assertSame('internal_blocked', $report['status']);
    }
}
