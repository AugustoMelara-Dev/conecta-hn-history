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
    $actionsDir = __DIR__ . '/../../app/Actions';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($actionsDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $basename = $file->getFilename();

        // If file directly references User:: (static call) or instanceof User (type check on Eloquent model),
        // it must carry the ARCHITECTURAL BRIDGE marker.
        if (preg_match('/\bUser::\w|instanceof\s+User\b/', $content)) {
            $hasBridge = str_contains($content, 'ARCHITECTURAL BRIDGE');
            expect($hasBridge)->toBeTrue("File {$basename} uses Eloquent User model without 'ARCHITECTURAL BRIDGE' documentation.");
        }
    }
});

test('decoupled actions do not import eloquent user model', function () {
    $decoupledActions = [
        __DIR__ . '/../../app/Actions/Identity/CreateNewUser.php',
        __DIR__ . '/../../app/Actions/Authentication/ResetPasswordAction.php',
    ];

    foreach ($decoupledActions as $file) {
        $content = file_get_contents($file);
        expect($content)->not->toContain('use App\Models\User;');
    }
});

test('decoupled actions document their fortify bridges', function () {
    $decoupledActions = [
        __DIR__ . '/../../app/Actions/Identity/CreateNewUser.php',
        __DIR__ . '/../../app/Actions/Authentication/ResetPasswordAction.php',
    ];

    foreach ($decoupledActions as $file) {
        $content = file_get_contents($file);
        expect($content)->toContain('ARCHITECTURAL BRIDGE');
    }
});

test('create new user uses projection port instead of direct ORM', function () {
    $content = file_get_contents(__DIR__ . '/../../app/Actions/Identity/CreateNewUser.php');
    expect($content)
        ->toContain('UserReadProjection')
        // Check actual code invocation, not comment mentions
        ->not->toContain('return User::findOrFail');
});

test('reset password action uses authenticatable contract', function () {
    $content = file_get_contents(__DIR__ . '/../../app/Actions/Authentication/ResetPasswordAction.php');
    expect($content)
        ->toContain('use Illuminate\Contracts\Auth\Authenticatable;')
        ->toContain('instanceof Authenticatable');
});
