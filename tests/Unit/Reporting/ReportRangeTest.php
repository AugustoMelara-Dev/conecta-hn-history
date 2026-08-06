<?php

declare(strict_types=1);

use App\Domain\Reporting\ValueObjects\ReportRange;

it('ReportRange.fromPreset handles unknown preset gracefully', function () {
    $range = ReportRange::fromPreset('invalid-preset');
    expect($range->days())->toBeGreaterThan(0);
});

it('ReportRange has comparison for non-custom presets', function () {
    $range = ReportRange::last7Days();
    expect($range->hasComparison())->toBeTrue();
});

it('ReportRange custom uses duration for comparison', function () {
    $start = \Carbon\CarbonImmutable::parse('2026-01-01');
    $end = \Carbon\CarbonImmutable::parse('2026-01-31');
    $range = ReportRange::custom($start, $end);

    expect($range->days())->toBe(30);
    expect($range->hasComparison())->toBeTrue();
});

it('ReportRange qtd and ytd work', function () {
    expect(ReportRange::quarterToDate()->days())->toBeGreaterThan(0);
    expect(ReportRange::yearToDate()->days())->toBeGreaterThan(80);
});
