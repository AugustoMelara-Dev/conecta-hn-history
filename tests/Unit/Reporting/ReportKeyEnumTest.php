<?php

declare(strict_types=1);

use App\Domain\Reporting\Enums\ReportFormat;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;

it('ReportKey enum has 25 cases', function () {
    expect(count(ReportKey::cases()))->toBe(25);
});

it('ReportKey categories cover all', function () {
    $categories = array_unique(array_map(fn ($c) => $c->category(), ReportKey::cases()));
    expect(count($categories))->toBeGreaterThanOrEqual(5);
});

it('ReportFormat enum has 3 cases', function () {
    expect(ReportFormat::cases())->toHaveCount(3);
    expect(ReportFormat::Pdf->extension())->toBe('pdf');
    expect(ReportFormat::Pdf->mime())->toBe('application/pdf');
});

it('ReportRange today() returns valid range', function () {
    $range = ReportRange::today();
    expect($range->days())->toBe(0);
    expect($range->hasComparison())->toBeTrue();
});

it('ReportRange last30Days() returns 30 day range', function () {
    $range = ReportRange::last30Days();
    expect($range->days())->toBeGreaterThanOrEqual(29);
    expect($range->days())->toBeLessThanOrEqual(31);
});

it('ReportRange fromPreset handles all presets', function () {
    $presets = ['today', 'yesterday', '7d', '30d', 'qtd', 'ytd', 'unknown'];
    foreach ($presets as $preset) {
        $range = ReportRange::fromPreset($preset);
        expect($range->start)->not->toBeNull();
        expect($range->end)->not->toBeNull();
    }
});
