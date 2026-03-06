<?php

declare(strict_types=1);

use App\Infrastructure\Identity\Fortify\Actions\Identity\CreateNewUser;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * FortifyBindingTest - Explicit Contract Governance
 *
 * Enforces: All Fortify contracts are explicitly bound in the
 * ServiceProvider. No magic auto-discovery.
 */

test('creates new users contract is explicitly bound in the container', function () {
    $content = file_get_contents(__DIR__ . '/../../app/Providers/FortifyServiceProvider.php');

    // Assert the explicit binding call exists
    expect($content)->toContain('createUsersUsing');

    // Assert it references our specific Action class
    expect($content)->toContain('CreateNewUser');
});

test('orphaned reactivate user action has been eliminated', function () {
    expect(file_exists(__DIR__ . '/../../app/Actions/Identity/ReactivateUserAction.php'))->toBeFalse();
});
