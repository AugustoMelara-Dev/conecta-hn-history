<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * ProductCreated - Domain Event for Product Creation
 *
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 */
final readonly class ProductCreated implements ShouldDispatchAfterCommit
{
    private const SCHEMA_VERSION = 1;
    public function schemaVersion(): int { return self::SCHEMA_VERSION; }
    public function __construct(
        public int $productId,
        public string $tenantId,
        public string $name,
    ) {}

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'product_id' => $this->productId,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'occurred_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }
}
