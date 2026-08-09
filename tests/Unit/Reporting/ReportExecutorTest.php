<?php

declare(strict_types=1);

use App\Application\Reporting\ReportExecutor;
use App\Application\Reporting\ReportRegistry;
use App\Application\Reporting\Reports\RevenueByMonthReport;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;

it('RevenueByMonthReport returns rows and series', function () {
    $report = new RevenueByMonthReport;
    $range = ReportRange::last30Days();

    // Use mock tenant ID
    $data = $report->getData('test-tenant', $range);

    expect($data->rows)->toBeArray();
    expect($data->series)->toBeArray();
    expect($data->metadata)->toHaveKey('range');
});

it('ReportExecutor caches results', function () {
    $registry = new ReportRegistry;
    $registry->register(new RevenueByMonthReport);

    $executor = new ReportExecutor($registry);

    // First call
    $range = ReportRange::last30Days();
    $data1 = $executor->execute(ReportKey::RevenueByMonth, 'cache-test', $range);
    // Second call (should be cached)
    $data2 = $executor->execute(ReportKey::RevenueByMonth, 'cache-test', $range);

    expect($data1->rows)->toBe($data2->rows);
});

it('ReportExecutor throws on unknown report', function () {
    $registry = new ReportRegistry;
    $executor = new ReportExecutor($registry);
    $executor->execute(ReportKey::MrrArr, 'x', ReportRange::last7Days());
})->throws(InvalidArgumentException::class);
