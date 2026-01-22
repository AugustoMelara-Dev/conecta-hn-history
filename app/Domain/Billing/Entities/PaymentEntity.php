<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entities;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;

final readonly class PaymentEntity
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public Money $amount,
        public string $bank,
        public string $referenceNumber,
        public PaymentStatus $status,
        public ?string $proofUrl,
        public DateTimeImmutable $createdAt,
        public ?string $idempotencyKey = null,
        // OCC Version
        public int $version = 1
    ) {}
}
