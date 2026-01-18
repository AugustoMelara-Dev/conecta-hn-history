<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Billing;

use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\Payment;
use DateTimeImmutable;

/**
 * EloquentPaymentRepository - Infrastructure Adapter for Payment Persistence
 *
 * FIXED: Updated method signatures to match PaymentRepositoryInterface.
 * - findById() now accepts string $id and returns ?PaymentEntity
 * - findByTenantId() now accepts string $tenantId and returns PaymentEntity[]
 * - save() now accepts PaymentEntity (upsert pattern)
 * - Removed legacy create(array) method
 */
final class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function findById(string $id): ?PaymentEntity
    {
        $model = Payment::find($id);
        return $model ? $this->toEntity($model) : null;
    }

    public function findByTenantId(string $tenantId): array
    {
        return Payment::where('tenant_id', $tenantId)
            ->get()
            ->map(fn (Payment $model) => $this->toEntity($model))
            ->all();
    }

    public function save(PaymentEntity $payment): void
    {
        Payment::updateOrCreate(
            ['id' => $payment->id],
            [
                'tenant_id' => $payment->tenantId,
                'amount' => $payment->amount->amount,
                'currency' => $payment->amount->currency,
                'bank' => $payment->bank,
                'reference_number' => $payment->referenceNumber,
                'status' => $payment->status->value,
                'proof_url' => $payment->proofUrl,
            ]
        );
    }

    /**
     * Map Eloquent model to domain entity.
     */
    private function toEntity(Payment $model): PaymentEntity
    {
        return new PaymentEntity(
            id: (string) $model->id,
            tenantId: (string) $model->tenant_id,
            amount: Money::of((int) $model->amount, $model->currency ?? 'HNL'),
            bank: $model->bank,
            referenceNumber: $model->reference_number,
            status: PaymentStatus::from($model->status),
            proofUrl: $model->proof_url,
            createdAt: $model->created_at instanceof \DateTime 
                ? DateTimeImmutable::createFromMutable($model->created_at) 
                : new DateTimeImmutable($model->created_at ?? 'now'),
        );
    }
}
