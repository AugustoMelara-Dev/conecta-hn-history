<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use DateTimeInterface;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * ProductDeleted - Domain Event for Product Soft Deletion
 *
 * Signals that a product has been soft-deleted. Listeners may:
 * - Update search indexes
 * - Notify affected users
 * - Log audit trail
 */
final readonly class ProductDeleted implements ShouldDispatchAfterCommit
{
    private const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public int $productId,
        public string $tenantId,
        public string $name,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
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
            'product_id'     => $this->productId,
            'tenant_id'      => $this->tenantId,
            'name'           => $this->name,
            'occurred_at'    => $this->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * Reconstruct event from safe payload.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            productId:  (int)    ($payload['product_id'] ?? 0),
            tenantId:   (string) ($payload['tenant_id']  ?? ''),
            name:       (string) ($payload['name']       ?? ''),
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable(),
        );
    }
}
