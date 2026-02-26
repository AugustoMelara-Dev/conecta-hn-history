<?php

declare(strict_types=1);

/**
 * ContractUniquenessTest - Zombie Interface Detection
 *
 * Ensures critical interfaces have exactly ONE canonical definition.
 * Prevents "Zombie Architecture" where duplicate interfaces cause ambiguity.
 */

test('upcaster interface is unique and canonical', function () {
    // Canonical must exist
    expect(interface_exists(\App\Application\Shared\Contracts\UpcasterInterface::class))->toBeTrue();

    // Duplicates must NOT exist on disk
    $appDir = __DIR__ . '/../../app';

    expect(file_exists($appDir . '/Domain/Shared/Contracts/UpcasterInterface.php'))
        ->toBeFalse('Duplicate UpcasterInterface found in Domain.');

    expect(file_exists($appDir . '/Infrastructure/EventVersioning/UpcasterInterface.php'))
        ->toBeFalse('Duplicate UpcasterInterface found in Infrastructure.');
});
