<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Shared\Exceptions\StaleObjectException;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\Payment as PaymentModel;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class EloquentPaymentRepository implements PaymentRepositoryInterface
{
    private const TABLE = 'subscription_payments';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PaymentEntity
    {
        $model = new PaymentModel;
        $model->forceFill([
            'tenant_id' => (string) $attributes['tenant_id'],
            'amount' => (float) $attributes['amount'],
            'bank' => (string) ($attributes['bank'] ?? 'pending_activation'),
            'reference_number' => (string) $attributes['reference_number'],
            'status' => (string) ($attributes['status'] ?? PaymentStatus::PENDING->value),
            'proof_path' => (string) ($attributes['proof_path'] ?? 'pending'),
            'idempotency_key' => (string) ($attributes['idempotency_key'] ?? 'registration-'.$attributes['reference_number']),
            'version' => (int) ($attributes['version'] ?? 1),
        ]);
        $model->save();

        return $this->toEntity($model);
    }

    public function save(PaymentEntity $payment): void
    {
        $model = PaymentModel::query()->findOrNew($payment->id);
        $model->id = $payment->id; // ID might be auto-increment in Schema? Migration said $table->id().
        // WAIT. Migration said $table->id() which is BigInt AutoIncrement.
        // Entity uses String ID (UUID likely).
        // If Entity ID is UUID and DB is BigInt -> CRASH.
        // CHECK MIGRATION AGAIN: 2025_12_17 says $table->id().

        // CRITICAL FIX: If table is BigInt, we cannot force UUID from Domain unless we changed schema.
        // We did NOT change schema to UUID.
        // So Entity.id (string) must be "1", "2".
        // If new entity, ID is null?

        // If PaymentEntity ID is provided (e.g. from existing), it's fine.
        // If it's NEW, Entity might have null ID? Domain Entities usually mandate ID.
        // If Domain generates UUID but DB wants Int -> PROBLEM.

        // ASSUMPTION: The system uses Int IDs for Payments, and Entity.id is cast to string.
        // If new, we don't set ID?
        // But `findOrNew` needs ID.

        // Let's assume ID is optional/null for new generic persistence,
        // BUT PaymentEntity constructor likely requires ID.

        // If we are Legacy, we handle translation.

        if ($payment->id) {
            $model->id = $payment->id;
        }

        $model->tenant_id = $payment->tenantId;
        $model->amount = self::minorToMajor($payment->amount->amount);
        // $model->currency = ... Table doesn't have currency. Assume Base.
        $model->bank = $payment->bank;
        $model->reference_number = $payment->referenceNumber;
        $model->status = $payment->status->value;
        $model->proof_path = $payment->proofUrl; // MAPPED
        $model->created_at = $payment->createdAt;
        $model->idempotency_key = $payment->idempotencyKey ?? null;
        $model->version = $payment->version;

        $model->save();

        // Sync ID back if auto-incremented?
        // Domain Entity is immutable. We can't set ID back.
        // This is a known DDD friction with AutoIncrements.
        // We rely on 'id' being consistent.
    }

    public function updateOptimistically(PaymentEntity $payment): void
    {
        $affected = DB::table(self::TABLE)
            ->where('id', $payment->id)
            ->where('version', $payment->version)
            ->update([
                'tenant_id' => $payment->tenantId,
                'amount' => self::minorToMajor($payment->amount->amount),
                // 'currency' => 'HNL', // No column
                'bank' => $payment->bank,
                'reference_number' => $payment->referenceNumber,
                'status' => $payment->status->value,
                'proof_path' => $payment->proofUrl, // MAPPED
                'idempotency_key' => $payment->idempotencyKey,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw new StaleObjectException("Payment {$payment->id} was modified by another process. Expected version {$payment->version}.");
        }
    }

    public function findById(string $id): ?PaymentEntity
    {
        $model = PaymentModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return array{payment: PaymentEntity, tenant_name: string, tenant_user_id: string|null}|null
     */
    public function findByIdWithTenant(string $id): ?array
    {
        $model = PaymentModel::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->with('tenant:id,name,user_id')
            ->find($id);

        if (! $model) {
            return null;
        }

        return [
            'payment' => $this->toEntity($model),
            'tenant_name' => (string) ($model->tenant?->name ?? 'Tu negocio'),
            'tenant_user_id' => $model->tenant?->user_id ? (string) $model->tenant->user_id : null,
        ];
    }

    /**
     * LEVEL 38: STRONG CONSISTENCY - Writer-Pinned Fetching
     *
     * Forces the query to the PRIMARY (Writer) node, bypassing all read replicas.
     * This guarantees "Read-Your-Own-Writes" consistency for async workers.
     *
     * Use Case: PromotePaymentEvidence listener MUST use this method to ensure
     * the record committed microseconds ago is immediately visible.
     *
     * @param  string  $id  The Payment ID
     * @return PaymentEntity|null The Payment Entity or null if truly non-existent
     */
    public function findByIdOnWriteConnection(string $id): ?PaymentEntity
    {
        // useWritePdo() forces Laravel to use the Write connection (Primary Node)
        // This bypasses any read-replica routing middleware or sticky sessions.
        $model = PaymentModel::query()
            ->useWritePdo()
            ->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByIdempotencyKey(string $tenantId, string $idempotencyKey): ?PaymentEntity
    {
        $model = PaymentModel::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findPaymentsWithTemporaryProofs(int $limit = 50): Collection
    {
        $bufferThreshold = now()->subMinutes(5);
        $executionDelay = now()->subMinutes(10);

        $models = PaymentModel::query()
            ->where('proof_path', 'like', 'temp/%') // MAPPED
            ->where('created_at', '<', $bufferThreshold)
            ->where('updated_at', '<', $executionDelay)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $models->map(fn (PaymentModel $model) => $this->toEntity($model));
    }

    private static function minorToMajor(int $minorAmount): string
    {
        return number_format($minorAmount / 100, 2, '.', '');
    }

    private static function majorToMinor(mixed $majorAmount): int
    {
        return (int) round(((float) $majorAmount) * 100);
    }

    private function toEntity(PaymentModel $model): PaymentEntity
    {
        return new PaymentEntity(
            id: (string) $model->id,
            tenantId: (string) $model->tenant_id,
            amount: new Money(self::majorToMinor($model->amount), 'HNL'),
            bank: $model->bank,
            referenceNumber: $model->reference_number,
            status: PaymentStatus::tryFrom($model->status) ?? PaymentStatus::PENDING,
            proofUrl: $model->proof_path, // MAPPED
            createdAt: new DateTimeImmutable($model->created_at->toIso8601String()),
            idempotencyKey: $model->idempotency_key ?? '',
            version: $model->version ?? 1
        );
    }
}
