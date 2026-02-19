<?php

declare(strict_types=1);

/**
 * ValidationCouplingTest - Framework Coupling Governance
 *
 * Enforces: Actions MUST NOT couple validation directly to
 * Eloquent models via Rule::unique(User::class) or Rule::unique('users').
 * All uniqueness checks must go through the UserUniquenessCheckerInterface port.
 */

test('actions do not couple validation to eloquent models', function () {
    $actions = [
        __DIR__ . '/../../app/Actions/Identity/UpdateUserProfileInformation.php',
        __DIR__ . '/../../app/Actions/Identity/CreateNewUser.php',
    ];

    foreach ($actions as $action) {
        $content = file_get_contents($action);

        // Must not use Illuminate\Validation\Rule for unique checks
        expect($content)->not->toContain('Rule::unique(');

        // Must not reference User::class in validation context
        expect($content)->not->toContain("Rule::unique(User::class)");
        expect($content)->not->toContain("Rule::unique('users')");
    }
});

test('actions use domain-safe UniqueUser rule', function () {
    $actions = [
        __DIR__ . '/../../app/Actions/Identity/UpdateUserProfileInformation.php',
        __DIR__ . '/../../app/Actions/Identity/CreateNewUser.php',
    ];

    foreach ($actions as $action) {
        $content = file_get_contents($action);

        expect($content)->toContain('UniqueUser');
        expect($content)->toContain('UserUniquenessCheckerInterface');
    }
});
