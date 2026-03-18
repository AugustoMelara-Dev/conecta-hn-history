<?php

declare(strict_types=1);

/**
 * ConventionComplianceTest - Namespace Convention Governance
 *
 * Enforces the authoritative convention:
 *   Ports live at App\Application\{Context}\Contracts (NOT App\Domain\{Context}\Ports)
 *
 * Prevents duplicate interface definitions from being created at alternative paths.
 */
test('user read projection adheres to application contracts convention', function () {
    // Canonical path: App\Application\Identity\Contracts
    expect(interface_exists(\App\Application\Identity\Contracts\UserReadProjection::class))->toBeTrue();

    // Rejected alternative: App\Domain\Identity\Ports (must NOT exist)
    expect(interface_exists('App\Domain\Identity\Ports\UserReadProjection'))->toBeFalse();
});
