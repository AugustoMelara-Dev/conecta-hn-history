<?php

declare(strict_types=1);

use App\Application\Shared\Contracts\TenantAware;
use App\Models\User;

/**
 * TenantAwarenessTest - Schema Decoupling Governance
 *
 * Enforces:
 * 1. User model implements TenantAware contract
 * 2. ResetPasswordAction uses TenantAware::getTenantId() instead of implicit $user->tenant_id
 */
test('user model implements tenant aware contract', function () {
    $user = new User;
    expect($user)->toBeInstanceOf(TenantAware::class);
    expect(method_exists($user, 'getTenantId'))->toBeTrue();
});

test('reset password action requires tenant aware user', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Authentication/ResetPasswordAction.php');
    expect($content)
        ->toContain('TenantAware')
        ->toContain('getTenantId()')
        ->not->toContain('$user->tenant_id');
});
