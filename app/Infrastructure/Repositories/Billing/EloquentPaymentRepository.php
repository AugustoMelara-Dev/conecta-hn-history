<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Billing;

use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;

/**
 * EloquentPaymentRepository - Infrastructure Adapter for Payment Persistence
 */
final class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    public function findById(int $id): ?Payment
    {
        return Payment::find($id);
    }

    public function findByTenantId(int $tenantId): array
    {
        return Payment::where('tenant_id', $tenantId)->get()->all();
    }

    public function create(array $attributes): Payment
    {
        return Payment::create($attributes);
    }

    public function save(Payment $payment): void
    {
        $payment->save();
    }
}
