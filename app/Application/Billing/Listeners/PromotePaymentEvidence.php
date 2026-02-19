<?php

declare(strict_types=1);

namespace App\Application\Billing\Listeners;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Events\PaymentReported;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use App\Domain\Shared\Exceptions\StaleObjectException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Throwable;

/**
 * PromotePaymentEvidence - "Smart Merge" with Self-Healing (Level 26)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CONCURRENCY: RETRY-ON-CONFLICT + SMART MERGE + SELF-HEALING             ║
 * ║  1. Fresh Read (Inside Loop).                                            ║
 * ║  2. Idempotency Check (Already Promoted?).                               ║
 * ║  3. Promote S3 (With Inference Recovery).                                ║
 * ║  4. Apply ONLY new path to FRESH entity (Preserving Admin changes).      ║
 * ║  5. Atomic Optimistic Update.                                            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class PromotePaymentEvidence implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public bool $afterCommit = true;

    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private DocumentStorageServiceInterface $documentStorage,
        private LoggerInterface $logger,
    ) {}

    public function handle(PaymentReported $event): void
    {
        $this->logger->info("PromotePaymentEvidence: Processing Payment {$event->paymentId}");

        $attempts = 0;
        $cachedPermanentPath = null;

        do {
            $attempts++;

            try {
                // 1. RE-HYDRATION (Fresh Read from PRIMARY NODE)
                // LEVEL 38: STRONG CONSISTENCY - Writer-Pinned Fetching
                // Bypasses read replicas to guarantee "Read-Your-Own-Writes".
                $payment = $this->paymentRepository->findByIdOnWriteConnection($event->paymentId);

                if (! $payment) {
                    // LEVEL 38+40: DETERMINISTIC FAIL-FAST WITH OBSERVABILITY
                    // If the record is missing on the PRIMARY NODE, it is DEFINITIVE.
                    // Using fail() instead of delete() to:
                    // 1. Trigger the failed() hook for logging
                    // 2. Populate the failed_jobs table for SRE visibility
                    // 3. Enable alerting on Zombie Events
                    $this->fail(new RuntimeException(
                        "ZOMBIE EVENT: Payment {$event->paymentId} not found on Primary Node (Transaction Rollback or Deletion)."
                    ));

                    return;
                }

                // 2. VERIFICATION (Idempotency Check)
                $proofUrl = $payment->proofUrl;
                if (! $proofUrl) {
                    return;
                }

                if (! str_starts_with($proofUrl, 'temp')) {
                    $this->logger->info("PromotePaymentEvidence: Payment {$payment->id} evidence already promoted. Skipping.");

                    return;
                }

                // 3. THE PROMOTION
                $permanentPath = $cachedPermanentPath;
                if (! $permanentPath) {
                    $permanentPath = $this->executeSafePromotion($payment->id, $proofUrl);
                    $cachedPermanentPath = $permanentPath;
                }

                // 4. SMART MERGE (Apply ONLY our change to FRESH entity)
                $updatedPayment = new PaymentEntity(
                    id: $payment->id,
                    tenantId: $payment->tenantId,
                    amount: $payment->amount,
                    bank: $payment->bank,
                    referenceNumber: $payment->referenceNumber,
                    status: $payment->status,
                    proofUrl: $permanentPath, // APPLY CHANGE
                    createdAt: $payment->createdAt,
                    idempotencyKey: $payment->idempotencyKey,
                    version: $payment->version
                );

                // 5. ATOMIC OPTIMISTIC UPDATE
                $this->paymentRepository->updateOptimistically($updatedPayment);

                $this->logger->info("PromotePaymentEvidence: Successfully promoted to {$permanentPath}");
                break; // Success

            } catch (StaleObjectException $e) {
                if ($attempts >= self::MAX_ATTEMPTS) {
                    $this->logger->error("PromotePaymentEvidence: Failed to update DB after {$attempts} attempts due to contention.", [
                        'payment_id' => $event->paymentId,
                    ]);
                    throw $e;
                }
                usleep($attempts * 100000); // 100ms Backoff
            } catch (Throwable $e) {
                // Check if we can retry the job (Queue level)
                $this->logger->error("PromotePaymentEvidence: Failed attempt {$attempts}: ".$e->getMessage());
                throw $e;
            }

        } while ($attempts < self::MAX_ATTEMPTS);
    }

    /**
     * Executes S3 Promotion.
     *
     * NOTE (LEVEL 41): Inference Recovery was removed because `promote()` now generates
     * a NEW UUID for permanent paths. The old `str_replace('temp', 'permanent')` logic
     * was dead code - the inferred path would never exist.
     */
    private function executeSafePromotion(string $paymentId, string $tempPath): string
    {
        $this->logger->info("PromotePaymentEvidence: Promoting artifact for Payment {$paymentId}");

        return $this->documentStorage->promote($tempPath);
    }

    public function failed(PaymentReported $event, Throwable $exception): void
    {
        $this->logger->critical("TERMINAL FAILURE: Could not promote evidence for Payment {$event->paymentId}. Artifact is stuck in TEMP storage.", [
            'payment_id' => $event->paymentId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
