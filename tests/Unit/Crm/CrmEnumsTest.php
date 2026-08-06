<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\ActivityType;
use App\Domain\Crm\Enums\CustomFieldType;
use App\Domain\Crm\Enums\DealStatus;
use App\Domain\Crm\Enums\LifecycleStage;

it('LifecycleStage enum has expected cases and probabilities', function () {
    expect(LifecycleStage::cases())->toHaveCount(6);
    expect(LifecycleStage::Won->probability())->toBe(100);
    expect(LifecycleStage::Lost->probability())->toBe(0);
    expect(LifecycleStage::Lead->probability())->toBe(10);
});

it('DealStatus enum has 3 states', function () {
    expect(DealStatus::cases())->toHaveCount(3);
    expect(DealStatus::Open->color())->toBe('info');
    expect(DealStatus::Won->color())->toBe('success');
    expect(DealStatus::Lost->color())->toBe('danger');
});

it('ActivityType enum has icons and colors', function () {
    expect(ActivityType::Call->icon())->toBe('heroicon-o-phone');
    expect(ActivityType::Email->color())->toBe('primary');
});

it('CustomFieldType enum has 8 types', function () {
    expect(CustomFieldType::cases())->toHaveCount(8);
});
