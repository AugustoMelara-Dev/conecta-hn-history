<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Results\ReportPaymentResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Events\PaymentReported;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * ReportPaymentHandler - Zero-Waste Architecture (Level 12)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STRATEGY: PURIFIED INSERT-OR-RECOVER                                    ║
 * ║  1. Upload Temp (Quarantine) - Fast                                      ║
 * ║  2. Insert-or-Recover (Try/Catch) - Atomic                               ║
 * ║  3. Dispatch Event (PaymentReported)                                     ║
 * ║  4. EXIT (No Promotion)                                                  ║
 * ║  RESULT: Fast User Response. Promotion is Async via Listener.            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ReportPaymentHandler implements ReportPaymentHandlerInterface
{
    use LogSanitizer;

    private const MAX_ATTEMPTS = 20; // 20 reports per minute (High Throughput)

    private const DECAY_SECONDS = 60;

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private DocumentStorageServiceInterface $documentStorage,
        private EventDispatcherInterface $eventDispatcher,
        private UuidGeneratorInterface $uuidGenerator,
        private LoggerInterface $logger,
        private \App\Infrastructure\Security\ResilientRateLimiter $rateLimiter
    ) {}

    public function handle(ReportPaymentCommand $command): ReportPaymentResult
    {
        $limitKey = 'billing:report_payment:'.$command->tenantId;

        // STRATEGY: FAIL-OPEN
        // If Redis dies, we ACCEPT the payment. We prioritize Revenue over Traffic Control.
        return $this->rateLimiter->attempt($limitKey, self::MAX_ATTEMPTS, self::DECAY_SECONDS, function () use ($command) {

            // 1. GENERATE IDENTITY
            $paymentId = $this->uuidGenerator->generate();

            // 2. VERIFY PRESENCE (Trust But Verify) - *OUTSIDE TRANSACTION*
            // CRITICAL HYGIENE: Never perform HTTP I/O inside a DB Transaction.
            // This prevents holding Row Locks while waiting for S3.
            $tempPath = $command->proofOfPaymentPath;
            if ($tempPath) {
                // SECURITY: Enforce Tenant Isolation
                $expectedPrefix = sprintf('temp/%s/', $command->tenantId);
                if (! str_starts_with($tempPath, $expectedPrefix)) {
                    throw new \InvalidArgumentException('Security Violation: Storage key does not belong to this tenant.');
                }

                // "HeadObject" check
                $this->documentStorage->verifyPresence($tempPath);
            }

            // 3. PREPARE ENTITY
            $payment = new PaymentEntity(
                id: $paymentId,
                tenantId: $command->tenantId,
                amount: new Money($command->amountInCents, $command->currency),
                bank: $command->bank,
                referenceNumber: $command->paymentReference,
                status: PaymentStatus::PENDING,
                proofUrl: $tempPath,
                createdAt: new DateTimeImmutable,
                idempotencyKey: $command->idempotencyKey
            );

            try {
                // 4. ATOMIC TRAP (Insert-or-Recover)
                // Retries: 3 (Handle Deadlocks automatically)
                DB::transaction(function () use ($payment, $command) {
                    $this->paymentRepository->save($payment);

                    // Dispatch Event
                    $this->eventDispatcher->dispatch(new PaymentReported(
                        paymentId: $payment->id,
                        tenantId: $payment->tenantId,
                        amount: $payment->amount->amount,
                        currency: $payment->amount->currency,
                        status: $payment->status->value,
                        idempotencyKey: $command->idempotencyKey,
                        occurredAt: new DateTimeImmutable
                    ));
                }, 3); // Auto-Retry on Deadlock (Error 1213)

                return ReportPaymentResult::fromEntity($payment);

            } catch (QueryException $e) {
                // SCENARIO 2: COLLISION (We Lost)
                // Code 23000 = Integrity Constraint Violation (Duplicate Key)
                if ($e->getCode() !== '23000' && $e->errorInfo[1] !== 1062) {
                    throw $e; // Real error
                }

                // RECOVERY LOGIC
                return $this->handleDuplicate($command, $e);
            }
        }, failOpen: true); // <--- CRITICAL: FAIL-OPEN
    }

    private function handleDuplicate(ReportPaymentCommand $command, QueryException $e): ReportPaymentResult
    {
        $existingPayment = $this->paymentRepository->findByIdempotencyKey(
            $command->tenantId,
            $command->idempotencyKey
        );

        if (! $existingPayment) {
            throw new \RuntimeException('Concurrency anomaly: Duplicate detected but record missing.', 0, $e);
        }

        $this->logger->info('Recovered existing payment for duplicate report', [
            'tenant_id' => $command->tenantId,
            'key' => $command->idempotencyKey,
            'existing_id' => $existingPayment->id,
        ]);

        // LIVENESS FIX: Kick the machine if it's stuck.
        // LEVEL 34: TIME-GATED LIVENESS (Thundering Herd Protection)
        // Only re-dispatch if the payment is older than 5 minutes.
        // This prevents "Impatience Floods" where users spam retry immediately.
        $stuckThreshold = new DateTimeImmutable('-5 minutes');

        if (
            $existingPayment->proofUrl
            && str_starts_with($existingPayment->proofUrl, 'temp/')
            && $existingPayment->createdAt < $stuckThreshold
        ) {
            $this->logger->warning('Re-dispatching Promotion for stuck payment (Stale > 5m)', ['id' => $existingPayment->id]);

            $this->eventDispatcher->dispatch(new PaymentReported(
                paymentId: $existingPayment->id,
                tenantId: $existingPayment->tenantId,
                amount: $existingPayment->amount->amount,
                currency: $existingPayment->amount->currency,
                status: $existingPayment->status->value,
                idempotencyKey: $command->idempotencyKey,
                occurredAt: new DateTimeImmutable
            ));
        }

        return ReportPaymentResult::fromEntity($existingPayment);
    }
}
