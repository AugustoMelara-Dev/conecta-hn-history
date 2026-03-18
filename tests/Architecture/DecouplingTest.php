<?php

declare(strict_types=1);

/**
 * DecouplingTest - Framework Coupling Governance
 *
 * Enforces:
 * - DF-01: Validation rules must not couple to Eloquent Model classes.
 * - DF-02: Known Eloquent bridges must be explicitly documented.
 * - FC-01: ResetPasswordAction depends on Authenticatable contract, not App\Models\User.
 * - FC-02: CreateNewUser uses UserReadProjection port, not User::findOrFail.
 */
test('update user profile info does not rely on user model for validation rules', function () {
    $content = file_get_contents(__DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Identity/UpdateUserProfileInformation.php');
    expect($content)->not->toContain('Rule::unique(User::class)');
});

test('create new user documents the architectural bridge', function () {
    $content = file_get_contents(__DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Identity/CreateNewUser.php');
    expect($content)->toContain('ARCHITECTURAL BRIDGE');
});

test('reset password action depends on contract not concrete model', function () {
    $content = file_get_contents(__DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Authentication/ResetPasswordAction.php');
    expect($content)->not->toContain('use App\\Models\\User;');
    expect($content)->toContain('use App\Application\Shared\Contracts\TenantAwareAuthenticatable;');
});

test('create new user action uses projection port not direct ORM', function () {
    $content = file_get_contents(__DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Identity/CreateNewUser.php');
    expect($content)->toContain('UserReadProjection');
    // Check for actual code invocation, not comment mentions
    expect($content)->not->toContain('return User::findOrFail');
    expect($content)->not->toContain('use App\\Models\\User;');
});
