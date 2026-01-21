<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * PaymentReported - Domain Event for Manual Payment Submission
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SCHEMA VERSIONED: Enables future upcasting without breaking consumers.  ║
 * ║  TRANSACTIONAL: Implements ShouldDispatchAfterCommit for Outbox safety.  ║
 * ║  TYPE SAFE: Uses string IDs (UUID/ULID compatible).                      ║
 * ║  TEMPORAL ACCURACY: occurredAt is immutable, set at creation time.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class PaymentReported implements ShouldDispatchAfterCommit
{
    public const SCHEMA_VERSION = 4;

    public function __construct(
        public string $paymentId,
        public string $tenantId,
        public int $amountInCents,
        public string $currency,
        public string $reference,
        public ?string $idempotencyKey = null,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {}

    /**
     * Reconstruct event from safe payload.
     * 
     * Essential for eliminating "Metadata Bomb" (extra keys) and 
     * resolving Case Mismatch (snake vs camel).
     */
    public static function fromPayload(array $payload): self
    {
        $occurredAt = isset($payload['occurred_at']) 
            ? new DateTimeImmutable($payload['occurred_at']) 
            : new DateTimeImmutable();

        return new self(
            paymentId: (string) ($payload['payment_id'] ?? ''),
            tenantId: (string) ($payload['tenant_id'] ?? ''),
            amountInCents: (int) ($payload['amount_in_cents'] ?? 0),
            currency: (string) ($payload['currency'] ?? 'HNL'),
            reference: (string) ($payload['reference'] ?? ''),
            idempotencyKey: isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : null,
            occurredAt: $occurredAt,
            schemaVersion: (int) ($payload['schema_version'] ?? self::SCHEMA_VERSION),
        );
    }

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'payment_id' => $this->paymentId,
            'tenant_id' => $this->tenantId,
            'amount_in_cents' => $this->amountInCents,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'idempotency_key' => $this->idempotencyKey,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
