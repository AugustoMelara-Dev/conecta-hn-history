<?php

declare(strict_types=1);

namespace Tests\Unit\TenantGovernance;

use App\Application\TenantGovernance\VerticalCapabilityScorecard;
use App\Domain\TenantGovernance\Enums\Capability;
use Tests\TestCase;

final class VerticalCapabilityScorecardTest extends TestCase
{
    public function test_scorecard_reports_readiness_for_all_business_types_from_manifest(): void
    {
        $scorecard = app(VerticalCapabilityScorecard::class)->byBusinessType();

        self::assertSame(['retail', 'food', 'service', 'hybrid'], array_keys($scorecard));

        foreach (['retail', 'food', 'service', 'hybrid'] as $businessType) {
            self::assertContains(Capability::AI_CONTENT->value, $scorecard[$businessType]['capabilities']);
            self::assertSame('ready', $scorecard[$businessType]['readiness']['ai_content']['status']);
            self::assertSame('partial', $scorecard[$businessType]['readiness']['billing']['status']);
            self::assertSame('partial', $scorecard[$businessType]['readiness']['experience']['status']);
            self::assertContains(
                $scorecard[$businessType]['readiness']['marketing']['status'],
                ['ready', 'partial', 'missing'],
            );
            self::assertContains(
                $scorecard[$businessType]['readiness']['operations']['status'],
                ['ready', 'partial', 'missing'],
            );
            self::assertContains(
                $scorecard[$businessType]['readiness']['analytics']['status'],
                ['ready', 'partial', 'missing'],
            );
            self::assertContains(
                $scorecard[$businessType]['readiness']['loyalty']['status'],
                ['ready', 'partial', 'missing'],
            );
        }

        self::assertSame('ready', $scorecard['retail']['readiness']['marketing']['status']);
        self::assertSame('partial', $scorecard['retail']['readiness']['loyalty']['status']);
        self::assertSame('partial', $scorecard['food']['readiness']['operations']['status']);
    }
}
