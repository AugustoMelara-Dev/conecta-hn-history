<?php

declare(strict_types=1);

/**
 * ContractUniquenessTest - Zombie Interface Detection
 *
 * Ensures critical interfaces have exactly ONE canonical definition.
 * Prevents "Zombie Architecture" where duplicate interfaces cause ambiguity.
 */
test('upcaster interface is unique and canonical', function () {
    // Canonical location: Domain\Shared\Contracts after the A5 Domain Purity migration.
    expect(interface_exists(\App\Domain\Shared\Contracts\UpcasterInterface::class))->toBeTrue();

    // Duplicate must NOT exist in the legacy Infrastructure\EventVersioning chain.
    $appDir = __DIR__.'/../../app';

    expect(file_exists($appDir.'/Infrastructure/EventVersioning/UpcasterInterface.php'))
        ->toBeFalse('Duplicate UpcasterInterface found in Infrastructure EventVersioning.');
});
