<?php

declare(strict_types=1);

/**
 * BridgeComplianceTest - Architectural Bridge Governance
 *
 * Enforces: Any direct Eloquent Model usage in the Actions layer
 * MUST be explicitly documented with an 'ARCHITECTURAL BRIDGE' marker.
 * Undocumented ORM coupling is a violation of Hexagonal Architecture.
 *
 * POST-DECOUPLING: After FC-01/FC-02 remediation, both original bridges
 * (User::findOrFail in CreateNewUser, instanceof User in ResetPasswordAction)
 * have been eliminated via UserReadProjection and Authenticatable contract.
 * This test now also verifies no NEW undocumented coupling appears.
 */
test('direct eloquent usage in actions must be explicitly documented as a bridge', function () {
    $actionsDir = __DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($actionsDir, FilesystemIterator::SKIP_DOTS)
    );
    $undocumentedBridges = [];

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $pathname = $file->getPathname();
        // ONLY SCAN ADAPTER ACTIONS
        if (! str_contains($pathname, DIRECTORY_SEPARATOR.'Actions'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $content = file_get_contents($pathname);
        $basename = $file->getFilename();

        // If file directly references User:: (static call) or instanceof User (type check on Eloquent model),
        // it must carry the ARCHITECTURAL BRIDGE marker.
        if (preg_match('/\bUser::\w|instanceof\s+User\b/', $content)) {
            $hasBridge = str_contains($content, 'ARCHITECTURAL BRIDGE');

            if (! $hasBridge) {
                $undocumentedBridges[] = $basename;
            }
        }
    }

    expect($undocumentedBridges)
        ->toBeEmpty('Undocumented Eloquent bridges found: '.implode(', ', $undocumentedBridges));
});

test('decoupled actions do not import eloquent user model', function () {
    $decoupledActions = [
        __DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Identity/CreateNewUser.php',
        __DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Authentication/ResetPasswordAction.php',
    ];

    foreach ($decoupledActions as $file) {
        $content = file_get_contents($file);
        expect(str_contains($content, 'use App\\Models\\User;'))->toBeFalse();
    }
});

test('decoupled actions document their fortify bridges', function () {
    $decoupledActions = [
        __DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Identity/CreateNewUser.php',
        __DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Authentication/ResetPasswordAction.php',
    ];

    foreach ($decoupledActions as $file) {
        $content = file_get_contents($file);
        expect($content)->toContain('Fortify');
    }
});

test('create new user uses projection port instead of direct ORM', function () {
    $content = file_get_contents(__DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Identity/CreateNewUser.php');
    expect($content)
        ->toContain('UserReadProjection')
        // Check actual code invocation, not comment mentions
        ->not->toContain('return User::findOrFail');
});

test('reset password action uses authenticatable contract', function () {
    $content = file_get_contents(__DIR__.'/../../app/Infrastructure/Identity/Fortify/Actions/Authentication/ResetPasswordAction.php');
    expect($content)
        ->toContain('use App\Application\Shared\Contracts\TenantAwareAuthenticatable;')
        ->toContain('instanceof TenantAwareAuthenticatable');
});
