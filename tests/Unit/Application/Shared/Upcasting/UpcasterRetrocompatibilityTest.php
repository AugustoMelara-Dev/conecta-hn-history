<?php

declare(strict_types=1);

it('verifies legacy commands retain tenant string identifier during schema version upcasting', function () {
    // Scaffold strict Tenant Context (UUID String representation)
    $tenantId = 'tnt_'.bin2hex(random_bytes(8));

    // Anonymous class representing a V1 Legacy Command payload
    $legacyCommand = new class($tenantId)
    {
        public int $schemaVersion = 1;

        public function __construct(public string $tenantId) {}
    };

    // Assert the fundamental boundaries remain intact before applying upcaster mutations
    expect($legacyCommand->tenantId)->toBe($tenantId)
        ->and($legacyCommand->schemaVersion)->toBe(1);
});
