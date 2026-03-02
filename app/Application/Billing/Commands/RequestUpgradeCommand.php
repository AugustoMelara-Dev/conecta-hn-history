<?php

declare(strict_types=1);

namespace App\Application\Billing\Commands;

use App\Application\Shared\Contracts\IdempotentCommandInterface;

final readonly class RequestUpgradeCommand implements IdempotentCommandInterface, \App\Application\Shared\Contracts\VersionedCommandInterface
{
    use \App\Application\Shared\Traits\HasSchemaVersion;

    public function __construct(
        public string $tenantId,
        public string $newPlanIdentifier,
        public string $currency,
        public string $idempotencyKey, // Used for Middleware (Redis)
        public string $clientIdempotencyKey, // Used for Deterministic Resolution (MySQL) - Usually same source
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }
}
