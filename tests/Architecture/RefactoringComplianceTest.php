<?php

declare(strict_types=1);

/**
 * RefactoringComplianceTest - Contract Unification Governance
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  Enforces TenantAwareAuthenticatable usage across all Actions and      ║
 * ║  validates that Application Layer DTOs have no HTTP dependencies.      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

use App\Application\Interactions\DTOs\InteractionData;

test('UpdateUserPassword uses TenantAwareAuthenticatable contract', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Authentication/UpdateUserPassword.php');

    expect($content)->toContain('TenantAwareAuthenticatable');
    expect($content)->toContain('getTenantId()');
    expect($content)->toContain('getAuthIdentifier()');

    $hasMagicTenant = preg_match('/\$user\s*->\s*tenant_id/', $content);
    expect($hasMagicTenant)->toBe(0);

    $hasMagicId = preg_match('/\$user->id[^e]/', $content);
    expect($hasMagicId)->toBe(0);
});

test('UpdateUserProfileInformation uses TenantAwareAuthenticatable contract', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Identity/UpdateUserProfileInformation.php');

    expect($content)->toContain('TenantAwareAuthenticatable');
    expect($content)->toContain('getTenantId()');
    expect($content)->toContain('getAuthIdentifier()');

    $hasMagicTenant = preg_match('/\$user\s*->\s*tenant_id/', $content);
    expect($hasMagicTenant)->toBe(0);

    $hasMagicId = preg_match('/\$user->id[^e]/', $content);
    expect($hasMagicId)->toBe(0);
});

test('ResetPasswordAction uses TenantAwareAuthenticatable contract', function () {
    $content = file_get_contents(__DIR__.'/../../app/Actions/Authentication/ResetPasswordAction.php');

    expect($content)->toContain('TenantAwareAuthenticatable');
    expect($content)->toContain('getTenantId()');
    expect($content)->toContain('getAuthIdentifier()');

    $hasMagicTenant = preg_match('/\$user\s*->\s*tenant_id/', $content);
    expect($hasMagicTenant)->toBe(0);
});

test('interaction data dto does NOT have fromRequest factory (dependency rule)', function () {
    // ARCHITECTURAL GOVERNANCE: Application Layer DTOs must not depend on HTTP Request.
    // The fromRequest() factory was removed to restore the Dependency Rule.
    // Request→DTO mapping now lives in the Controller (Infrastructure Layer).
    expect((new \ReflectionClass(InteractionData::class))->hasMethod('fromRequest'))->toBeFalse(
        'InteractionData must not have fromRequest() — HTTP mapping belongs in the Controller'
    );
});
