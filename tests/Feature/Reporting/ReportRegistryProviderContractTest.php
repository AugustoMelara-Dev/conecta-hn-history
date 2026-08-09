<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ReportRegistry;
use App\Domain\Reporting\Enums\ReportKey;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReportRegistryProviderContractTest extends TestCase
{
    #[Test]
    public function provider_resolves_the_complete_canonical_report_registry(): void
    {
        $registry = app(ReportRegistry::class);
        $reports = $registry->all();

        self::assertCount(25, $reports);
        foreach ($reports as $report) {
            self::assertInstanceOf(ReportInterface::class, $report);
            self::assertTrue($registry->has($report->getKey()));
            self::assertSame($report, $registry->get($report->getKey()));
        }

        $actualKeys = array_map(
            static fn (ReportInterface $report): string => $report->getKey()->value,
            $reports,
        );
        $expectedKeys = array_map(
            static fn (ReportKey $key): string => $key->value,
            ReportKey::cases(),
        );
        sort($actualKeys);
        sort($expectedKeys);

        self::assertSame($expectedKeys, $actualKeys);
        self::assertCount(25, array_unique($actualKeys));

        $grouped = $registry->grouped();
        self::assertCount(25, array_merge(...array_values($grouped)));
    }
}
