<?php

declare(strict_types=1);

namespace App\Application\Billing\Commands;

use App\Application\Shared\Contracts\IdempotentCommandInterface;

final readonly class RequestUpgradeCommand implements \App\Application\Shared\Contracts\VersionedCommandInterface, IdempotentCommandInterface
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

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'tenantId' => $this->tenantId,
            'newPlanIdentifier' => $this->newPlanIdentifier,
            'currency' => $this->currency,
            'idempotencyKey' => $this->idempotencyKey,
            'clientIdempotencyKey' => $this->clientIdempotencyKey,
            'schemaVersion' => $this->schemaVersion,
        ];
    }
}
