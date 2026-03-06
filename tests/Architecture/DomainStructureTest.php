<?php

declare(strict_types=1);

use App\Infrastructure\Identity\Fortify\Actions\Identity\UpdateUserProfileInformation;

/**
 * DomainStructureTest - Screaming Architecture Governance
 *
 * Enforces: Actions reside in their correct Bounded Context subdirectory.
 * Profile updates are Identity lifecycle events, not Authentication events.
 */

test('update user profile information resides in identity namespace', function () {
    expect(class_exists(UpdateUserProfileInformation::class))->toBeTrue();
    expect((new ReflectionClass(UpdateUserProfileInformation::class))->getNamespaceName())
        ->toBe('App\Infrastructure\Identity\Fortify\Actions\Identity');
});

test('authentication namespace does not contain profile updates', function () {
    expect(class_exists('App\Infrastructure\Identity\Fortify\Actions\Authentication\UpdateUserProfileInformation'))->toBeFalse();
});
