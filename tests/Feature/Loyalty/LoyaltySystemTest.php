<?php

declare(strict_types=1);

use App\Models\LoyaltyLedger;
use App\Models\LoyaltyProgram;

// ═══════════════════════════════════════════════════════════════════════
// UNIT TESTS (No DB required)
// ═══════════════════════════════════════════════════════════════════════

// TEST 1: LoyaltyProgram.calculatePoints works correctly
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

// TEST 2: LoyaltyProgram.calculateRedemptionValue works correctly
it('calculates redemption value correctly', function () {
    $program = new LoyaltyProgram([
        'redemption_ratio' => 100, // 100 points = L.1
    ]);

    expect($program->calculateRedemptionValue(100))->toBe(1.0);
    expect($program->calculateRedemptionValue(250))->toBe(2.5);
    expect($program->calculateRedemptionValue(50))->toBe(0.5);
});

// TEST 3: Inactive program returns zero points
it('returns zero points when program is inactive', function () {
    $program = new LoyaltyProgram([
        'earn_ratio' => 10,
        'is_active' => false,
    ]);

    expect($program->calculatePoints(100))->toBe(0);
});

// TEST 4: LoyaltyLedger has correct type constants
it('has correct type constants', function () {
    expect(LoyaltyLedger::TYPE_EARN)->toBe('earn');
    expect(LoyaltyLedger::TYPE_REDEEM)->toBe('redeem');
    expect(LoyaltyLedger::TYPE_EXPIRE)->toBe('expire');
    expect(LoyaltyLedger::TYPE_ADJUSTMENT)->toBe('adjustment');
});

// ═══════════════════════════════════════════════════════════════════════
// INFRASTRUCTURE TESTS (Verify models have correct traits and relationships)
// ═══════════════════════════════════════════════════════════════════════

// TEST 5: LoyaltyLedger uses BelongsToTenant trait
it('LoyaltyLedger uses BelongsToTenant trait', function () {
    $traits = class_uses_recursive(LoyaltyLedger::class);
    expect($traits)->toContain(\App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant::class);
});

// TEST 6: LoyaltyProgram uses BelongsToTenant trait
it('LoyaltyProgram uses BelongsToTenant trait', function () {
    $traits = class_uses_recursive(LoyaltyProgram::class);
    expect($traits)->toContain(\App\Infrastructure\Persistence\Eloquent\Traits\BelongsToTenant::class);
});

// TEST 7: User model has loyaltyBalance method
it('User model has loyaltyBalance method', function () {
    expect(method_exists(\App\Models\User::class, 'loyaltyBalance'))->toBeTrue();
    expect(method_exists(\App\Models\User::class, 'loyaltyLedger'))->toBeTrue();
    expect(method_exists(\App\Models\User::class, 'activeLoyaltyBalance'))->toBeTrue();
});

// TEST 8: AwardLoyaltyPoints listener exists
it('AwardLoyaltyPoints listener exists', function () {
    expect(class_exists(\App\Listeners\AwardLoyaltyPoints::class))->toBeTrue();
    expect(method_exists(\App\Listeners\AwardLoyaltyPoints::class, 'handle'))->toBeTrue();
});
