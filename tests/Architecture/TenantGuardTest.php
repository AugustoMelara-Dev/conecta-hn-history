<?php

declare(strict_types=1);

/**
 * TenantGuardTest - D3 Invariant Governance
 *
 * Enforces: Every Action that dispatches a tenant-scoped Command
 * must validate tenant context BEFORE dispatch.
 *
 * EXCEPTION: CreateNewUser is the genesis entry point for tenant creation.
 * Requiring a pre-existing tenant_id would block the "Sign Up & Create
 * Organization" flow. This exception is formally documented here.
 */

test('actions must validate tenant context before dispatch', function () {
    $actions = [
        __DIR__ . '/../../app/Actions/Authentication/UpdateUserPassword.php',
        __DIR__ . '/../../app/Actions/Identity/UpdateUserProfileInformation.php',
        __DIR__ . '/../../app/Actions/Interactions/RecordInteractionAction.php',
    ];

    foreach ($actions as $action) {
        $content = file_get_contents($action);

        expect($content)->toContain('RuntimeException');
        expect($content)->toContain('tenant context');
    }
});

test('create new user is explicitly exempt from tenant guard', function () {
    $content = file_get_contents(__DIR__ . '/../../app/Actions/Identity/CreateNewUser.php');

    // Must NOT throw RuntimeException for missing tenant — it's the genesis flow
    expect($content)->not->toContain("throw new \\RuntimeException('Cannot");

    // Must allow nullable tenant_id
    expect($content)->toContain("?? null");

    // Must carry the formal exception marker
    expect($content)->toContain('DOMAIN INVARIANT EXCEPTION');
});
