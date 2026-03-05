<?php

declare(strict_types=1);

use App\Infrastructure\Identity\Fortify\Actions\Authentication\ResetPasswordAction;

/**
 * BindingIntegrityTest - Container Wiring Governance
 *
 * Validates that Fortify Action classes exist in the expected namespaces
 * and that stale phantom namespace references are eliminated.
 */

test('reset password action exists in authentication namespace', function () {
    expect(class_exists(ResetPasswordAction::class))->toBeTrue();
    expect((new ReflectionClass(ResetPasswordAction::class))->getNamespaceName())
        ->toBe('App\Actions\Authentication');
});

test('app service provider does not reference phantom fortify namespace', function () {
    $content = file_get_contents(__DIR__ . '/../../app/Providers/AppServiceProvider.php');
    expect($content)
        ->not->toContain('App\Actions\Fortify\ResetPasswordAction')
        ->not->toContain('ResetsUserPasswords'); // Binding moved to FortifyServiceProvider
});

test('reset password action enforces tenant context and delegates to bus', function () {
    $content = file_get_contents(__DIR__ . '/../../app/Actions/Authentication/ResetPasswordAction.php');
    expect($content)
        ->toContain('TenantAware')
        ->toContain('getTenantId()')
        ->toContain('CommandBusInterface')
        ->toContain('ResetUserPasswordCommand');
});

test('resets user passwords contract is not bound in app service provider', function () {
    // GOVERNANCE: FortifyServiceProvider is the Single Source of Truth for Fortify bindings.
    // AppServiceProvider must NOT shadow these with redundant binds.
    $content = file_get_contents(__DIR__ . '/../../app/Providers/AppServiceProvider.php');
    expect($content)->not->toContain('ResetsUserPasswords::class');
});

test('resets user passwords contract is canonically registered in fortify service provider', function () {
    // Ensures FortifyServiceProvider::boot() is the Single Source of Truth.
    $content = file_get_contents(__DIR__ . '/../../app/Providers/FortifyServiceProvider.php');
    expect($content)->toContain('resetUserPasswordsUsing');
});
