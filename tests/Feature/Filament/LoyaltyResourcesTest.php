<?php

declare(strict_types=1);

use App\Filament\App\Resources\LoyaltyProgramResource;
use App\Filament\App\Resources\LoyaltyRewardResource;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;

// ═══════════════════════════════════════════════════════════════════════
// INFRASTRUCTURE TESTS - Verify Resources are properly wired
// ═══════════════════════════════════════════════════════════════════════

// TEST 1: LoyaltyProgramResource exists and is configured
it('LoyaltyProgramResource is properly configured', function () {
    expect(class_exists(LoyaltyProgramResource::class))->toBeTrue();
    expect(LoyaltyProgramResource::getModel())->toBe(LoyaltyProgram::class);
    expect(LoyaltyProgramResource::getNavigationGroup())->toBe('Fidelización');
});

// TEST 2: LoyaltyRewardResource exists and is configured
it('LoyaltyRewardResource is properly configured', function () {
    expect(class_exists(LoyaltyRewardResource::class))->toBeTrue();
    expect(LoyaltyRewardResource::getModel())->toBe(LoyaltyReward::class);
    expect(LoyaltyRewardResource::getNavigationGroup())->toBe('Fidelización');
});

// TEST 3: LoyaltyProgramResource has correct pages
it('LoyaltyProgramResource has required pages', function () {
    $pages = LoyaltyProgramResource::getPages();
    
    expect($pages)->toHaveKeys(['index', 'create', 'edit']);
});

// TEST 4: LoyaltyRewardResource has correct pages
it('LoyaltyRewardResource has required pages', function () {
    $pages = LoyaltyRewardResource::getPages();
    
    expect($pages)->toHaveKeys(['index', 'create', 'edit']);
});

// TEST 5: Page classes exist
it('LoyaltyProgram page classes exist', function () {
    expect(class_exists(\App\Filament\App\Resources\LoyaltyProgramResource\Pages\ListLoyaltyPrograms::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\LoyaltyProgramResource\Pages\CreateLoyaltyProgram::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\LoyaltyProgramResource\Pages\EditLoyaltyProgram::class))->toBeTrue();
});

// TEST 6: LoyaltyReward page classes exist
it('LoyaltyReward page classes exist', function () {
    expect(class_exists(\App\Filament\App\Resources\LoyaltyRewardResource\Pages\ListLoyaltyRewards::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\LoyaltyRewardResource\Pages\CreateLoyaltyReward::class))->toBeTrue();
    expect(class_exists(\App\Filament\App\Resources\LoyaltyRewardResource\Pages\EditLoyaltyReward::class))->toBeTrue();
});
