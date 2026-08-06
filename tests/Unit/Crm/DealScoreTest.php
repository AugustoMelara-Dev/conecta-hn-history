<?php

declare(strict_types=1);

use App\Domain\Crm\ValueObjects\DealScore;

it('DealScore fromFactors classifies all tiers correctly', function () {
    $tiers = ['hot', 'warm', 'qualified', 'cold', 'unqualified'];
    foreach ($tiers as $tier) {
        $score = match ($tier) {
            'hot' => DealScore::fromFactors(85, []),
            'warm' => DealScore::fromFactors(70, []),
            'qualified' => DealScore::fromFactors(50, []),
            'cold' => DealScore::fromFactors(30, []),
            'unqualified' => DealScore::fromFactors(10, []),
        };
        expect($score->tier)->toBe($tier);
        expect($score->value)->toBeGreaterThan(0);
        expect($score->value)->toBeLessThanOrEqual(100);
    }
});

it('DealScore color() returns valid filament color', function () {
    $colors = ['hot' => 'danger', 'warm' => 'warning', 'qualified' => 'info', 'cold' => 'gray', 'unqualified' => 'gray'];
    foreach ($colors as $tier => $expectedColor) {
        $value = match ($tier) {
            'hot' => 85, 'warm' => 70, 'qualified' => 50, 'cold' => 30, 'unqualified' => 10,
        };
        $score = DealScore::fromFactors($value, []);
        expect($score->color())->toBe($expectedColor);
    }
});

it('DealScore rejects invalid value below 0', function () {
    DealScore::fromFactors(-1, []);
})->throws(InvalidArgumentException::class);

it('DealScore rejects invalid value above 100', function () {
    DealScore::fromFactors(101, []);
})->throws(InvalidArgumentException::class);
