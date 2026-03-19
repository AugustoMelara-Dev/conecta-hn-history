<?php

declare(strict_types=1);

use App\Models\LoyaltyLedger;
use App\Models\LoyaltyProgram;
use App\Models\User;

it('calculates points from order total correctly', function () {
    $program = new LoyaltyProgram([
        'earn_ratio' => 10,
        'is_active' => true,
    ]);

    expect($program->calculatePoints(100))->toBe(10);
    expect($program->calculatePoints(99))->toBe(9);
    expect($program->calculatePoints(5))->toBe(0);
    expect($program->calculatePoints(0))->toBe(0);
});

it('calculates redemption value correctly', function () {
    $program = new LoyaltyProgram([
        'redemption_ratio' => 100,
    ]);

    expect($program->calculateRedemptionValue(100))->toBe(1.0);
    expect($program->calculateRedemptionValue(250))->toBe(2.5);
    expect($program->calculateRedemptionValue(50))->toBe(0.5);
});

it('returns zero points when program is inactive', function () {
    $program = new LoyaltyProgram([
        'earn_ratio' => 10,
        'is_active' => false,
    ]);

    expect($program->calculatePoints(100))->toBe(0);
});

it('has correct type constants', function () {
    expect(LoyaltyLedger::TYPE_EARN)->toBe('earn');
    expect(LoyaltyLedger::TYPE_REDEEM)->toBe('redeem');
    expect(LoyaltyLedger::TYPE_EXPIRE)->toBe('expire');
    expect(LoyaltyLedger::TYPE_ADJUSTMENT)->toBe('adjustment');
});

it('LoyaltyLedger uses BelongsToTenant trait', function () {
    $traits = class_uses_recursive(LoyaltyLedger::class);
    expect($traits)->toContain(\App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant::class);
});

it('LoyaltyProgram uses BelongsToTenant trait', function () {
    $traits = class_uses_recursive(LoyaltyProgram::class);
    expect($traits)->toContain(\App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant::class);
});

it('User model exposes loyalty helpers', function () {
    $reflection = new ReflectionClass(User::class);

    expect($reflection->hasMethod('loyaltyBalance'))->toBeTrue();
    expect($reflection->hasMethod('loyaltyLedger'))->toBeTrue();
    expect($reflection->hasMethod('activeLoyaltyBalance'))->toBeTrue();
});

it('AwardLoyaltyPoints listener exists', function () {
    expect(class_exists(\App\Listeners\AwardLoyaltyPoints::class))->toBeTrue();
    expect((new ReflectionClass(\App\Listeners\AwardLoyaltyPoints::class))->hasMethod('handle'))->toBeTrue();
});
