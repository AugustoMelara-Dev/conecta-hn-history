<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Repositories;

use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\Payment;
use DateTimeImmutable;

/**
 * EloquentPaymentRepository - Infrastructure Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  INFRASTRUCTURE MAPPER: Translates between Domain Entity and DB Model.  ║
 * ║  ELOQUENT AWARE: Handles direct DB interactions via Active Record.      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    /**
     * Find a payment by primary identifier.
     * @throws \App\Domain\Billing\Exceptions\PaymentNotFoundException
     */
    public function findById(string $id): ?PaymentEntity
    {
        /** @var Payment|null $model */
        $model = Payment::find($id);

        if ($model === null) {
            // EXCEPTION TRANSLATION: Infrastructure (null) -> Domain (Exception)
            throw \App\Domain\Billing\Exceptions\PaymentNotFoundException::withId($id);
        }

        return $this->toEntity($model);
    }

    /**
     * Find payments by tenant ID.
     *
     * @return PaymentEntity[]
     */
    public function findByTenantId(string $tenantId): array
    {
        $models = Payment::where('tenant_id', $tenantId)->get();

        return $models->map(fn (Payment $model) => $this->toEntity($model))->toArray();
    }

    /**
     * Persist a payment entity.
     */
    public function save(PaymentEntity $payment): void
    {
        // Dehydrate Entity to Eloquent Model
        Payment::updateOrCreate(
            ['id' => $payment->id],
            [
                'tenant_id' => $payment->tenantId,
                'bank' => $payment->bank,
                'reference_number' => $payment->referenceNumber,
                'amount' => $payment->amount->amount, // Extract clean primitive
                'currency' => $payment->amount->currency,
                'status' => $payment->status->value, // Extract Enum string
                'receipt_path' => $payment->proofUrl,
                'created_at' => $payment->createdAt,
            ]
        );
    }

    /**
     * Map Eloquent Model to Domain Entity.
     */
    private function toEntity(Payment $model): PaymentEntity
    {
        return new PaymentEntity(
            id: (string) $model->id,
            tenantId: (string) $model->tenant_id,
            amount: Money::of((int) $model->amount, (string) $model->currency),
            bank: (string) $model->bank,
            referenceNumber: (string) $model->reference_number,
            status: PaymentStatus::from((string) $model->status),
            proofUrl: $model->receipt_path,
            createdAt: new DateTimeImmutable((string) $model->created_at)
        );
    }
}
