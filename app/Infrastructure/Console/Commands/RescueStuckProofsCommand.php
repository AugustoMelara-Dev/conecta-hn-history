<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use App\Domain\Shared\Exceptions\StaleObjectException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RescueStuckProofsCommand extends Command
{
    protected $signature = 'storage:rescue-proofs';

    protected $description = 'Garbage Collector for Broken Links: Promotes stuck temp files to permanent storage using OCC, Deferral, and Self-Healing Inference';

    private const MAX_ATTEMPTS = 3;

    private const LOCK_TTL = 600;

    // Explicit Table Name for Raw SQL
    private const TABLE = 'subscription_payments';

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private DocumentStorageServiceInterface $documentStorage,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $lock = Cache::lock('billing:rescue_stuck_proofs_mutex', self::LOCK_TTL);

        if (! $lock->get()) {
            $this->warn('Another instance is holding the lock. Skipping this run.');

            return 0;
        }

        try {
            $this->info('Starting Rescue Worker for Stuck Proofs (OCC + Singleton + Inference)...');

            return $this->executeLogic();
        } finally {
            $lock->release();
        }
    }

    private function executeLogic(): int
    {
        $stuckPayments = $this->paymentRepository->findPaymentsWithTemporaryProofs(50);

        if ($stuckPayments === []) {
            $this->info('No qualified stuck funds found. System healthy or busy.');

            return self::SUCCESS;
        }

        $processed = 0;
        $deferred = 0;

        foreach ($stuckPayments as $payment) {
            if ($this->processRescue($payment)) {
                $processed++;
            } else {
                $deferred++;
            }
        }

        $this->info("Rescue Run Complete. Processed: {$processed}, Deferred: {$deferred}");

        return self::SUCCESS;
    }

    private function processRescue(PaymentEntity $initialPayment): bool
    {
        $paymentId = $initialPayment->id;
        $tempPath = $initialPayment->proofUrl;

        if (! $tempPath) {
            return false;
        }

        $this->info("Attempting rescue for Payment {$paymentId} (Path: {$tempPath})");

        $cachedPermanentPath = null;
        $attempts = 0;

        do {
            $attempts++;

            try {
                $freshPayment = $this->paymentRepository->findById($paymentId);

                if (! $freshPayment) {
                    $this->warn("Payment {$paymentId} deleted during rescue.");

                    return true;
                }

                if (! str_starts_with($freshPayment->proofUrl ?? '', 'temp')) {
                    $this->info("Payment {$paymentId} already fixed. Skipping.");

                    return true;
                }

                if (! $cachedPermanentPath) {
                    try {
                        $cachedPermanentPath = $this->documentStorage->promote($tempPath);
                    } catch (RuntimeException $e) {
                        if (str_contains($e->getMessage(), 'not found')) {
                            // SELF-HEALING: Check if it was already moved but DB not updated (Split Brain)
                            $inferredPath = $this->tryInferenceRecovery($tempPath);

                            if ($inferredPath) {
                                $this->info(" -> Self-Healing: Found file in {$inferredPath}. Resyncing DB.");
                                $cachedPermanentPath = $inferredPath;
                            } else {
                                $this->markAsMissingEvidence($freshPayment);

                                return true;
                            }
                        } else {
                            throw $e;
                        }
                    }
                }

                $fixedPayment = new PaymentEntity(
                    id: $freshPayment->id,
                    tenantId: $freshPayment->tenantId,
                    amount: $freshPayment->amount,
                    bank: $freshPayment->bank,
                    referenceNumber: $freshPayment->referenceNumber,
                    status: $freshPayment->status,
                    proofUrl: $cachedPermanentPath,
                    createdAt: $freshPayment->createdAt,
                    idempotencyKey: $freshPayment->idempotencyKey,
                    version: $freshPayment->version
                );

                $this->paymentRepository->updateOptimistically($fixedPayment);

                $this->info(" -> Success. Moved to {$cachedPermanentPath}");

                return true;

            } catch (StaleObjectException $e) {
                if ($attempts >= self::MAX_ATTEMPTS) {
                    Log::error("RescueStuckProofs: Contention Timeout for Payment {$paymentId}. Deferring.");
                    $this->deferPayment($paymentId);

                    return false;
                }
                usleep($attempts * 100000);
            } catch (Throwable $e) {
                Log::error("Rescue failed for Payment {$paymentId}: ".$e->getMessage());
                $this->deferPayment($paymentId);
                $this->error(' -> Failed. Deferring execution for 10 mins.');

                return false;
            }

        } while (true);
    }

    private function tryInferenceRecovery(string $tempPath): ?string
    {
        // Infer: "temp/uuid.pdf" -> "permanent/uuid.pdf"
        $inferredPath = str_replace('temp', 'permanent', $tempPath);

        // Environment Aware Inference
        $diskName = config('filesystems.default', 's3');
        $storage = Storage::disk($diskName);

        if ($storage->exists($inferredPath)) {
            return $inferredPath;
        }

        return null;
    }

    private function deferPayment(string $paymentId): void
    {
        try {
            DB::table(self::TABLE)
                ->where('id', $paymentId)
                ->update(['updated_at' => now()]);
        } catch (Throwable $e) {
        }
    }

    private function markAsMissingEvidence(PaymentEntity $payment): void
    {
        try {
            $failedPayment = new PaymentEntity(
                id: $payment->id,
                tenantId: $payment->tenantId,
                amount: $payment->amount,
                bank: $payment->bank,
                referenceNumber: $payment->referenceNumber,
                status: $payment->status,
                proofUrl: null,
                createdAt: $payment->createdAt,
                idempotencyKey: $payment->idempotencyKey,
                version: $payment->version
            );

            $this->paymentRepository->updateOptimistically($failedPayment);
            $this->error(' -> Failed. File missing. Link removed.');

        } catch (Throwable $e) {
            $this->deferPayment($payment->id);
        }
    }
}
