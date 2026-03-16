<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Results\ReportPaymentResult;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Events\PaymentReported;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use App\Domain\Shared\ValueObjects\Money;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use DateTimeImmutable;
use App\Domain\Billing\Exceptions\PaymentProcessingException;
use Illuminate\Database\UniqueConstraintViolationException;
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

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private DocumentStorageServiceInterface $documentStorage,
        private EventDispatcherInterface $eventDispatcher,
        private UuidGeneratorInterface $uuidGenerator,
        private LoggerInterface $logger,
        private TransactionManagerInterface $transactionManager,
        private DataScrubberInterface $scrubber,
    ) {}

    public function handle(ReportPaymentCommand $command): ReportPaymentResult
    {
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
            // Atomic check + insert + event dispatch (retry handled by outer decorators)
            $finalPayment = $this->transactionManager->transaction(function () use ($payment, $command) {
                $existingPayment = $this->paymentRepository->findByIdempotencyKey(
                    $command->tenantId,
                    $command->idempotencyKey
                );

                if ($existingPayment) {
                    $this->logger->warning('Idempotency hit during transaction. Skipping creation.', [
                        'tenant_id' => $command->tenantId,
                        'key' => $command->idempotencyKey,
                    ]);

                    return $existingPayment;
                }

                $this->paymentRepository->save($payment);

                // Dispatch Event
                $this->eventDispatcher->dispatch(new PaymentReported(
                    paymentId: $payment->id,
                    tenantId: $payment->tenantId,
                    amountInCents: $payment->amount->amount,
                    currency: $payment->amount->currency,
                    reference: $payment->referenceNumber,
                    idempotencyKey: $command->idempotencyKey,
                    occurredAt: new DateTimeImmutable
                ));

                return $payment;
            });

            return ReportPaymentResult::fromEntity($finalPayment);

        } catch (UniqueConstraintViolationException $e) {
            // SCENARIO 2a: COLLISION via Eloquent (UniqueConstraintViolationException)
            // Eloquent wraps PDO constraint violations before they reach PaymentProcessingException.
            return $this->handleDuplicateFromConstraint($command);

        } catch (PaymentProcessingException $e) {
            // SCENARIO 2: COLLISION (We Lost)
            // Code 23000 = Integrity Constraint Violation (Duplicate Key)
            if (strval($e->getCode()) !== '23000' && ($e->errorInfo[1] ?? null) !== 1062) {
                throw $e; // Real error
            }

            // RECOVERY LOGIC
            return $this->handleDuplicate($command, $e);
        }
    }

    private function handleDuplicateFromConstraint(ReportPaymentCommand $command): ReportPaymentResult
    {
        $existingPayment = $this->paymentRepository->findByIdempotencyKey(
            $command->tenantId,
            $command->idempotencyKey
        );

        if (! $existingPayment) {
            throw new \RuntimeException('Concurrency anomaly: Unique constraint violated but record not found.');
        }

        $this->logger->info('Recovered existing payment (Eloquent constraint)', [
            'tenant_id' => $command->tenantId,
            'key' => $command->idempotencyKey,
            'existing_id' => $existingPayment->id,
        ]);

        return ReportPaymentResult::fromEntity($existingPayment);
    }

    private function handleDuplicate(ReportPaymentCommand $command, PaymentProcessingException $e): ReportPaymentResult
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
                amountInCents: $existingPayment->amount->amount,
                currency: $existingPayment->amount->currency,
                reference: $existingPayment->referenceNumber,
                idempotencyKey: $command->idempotencyKey,
                occurredAt: new DateTimeImmutable
            ));
        }

        return ReportPaymentResult::fromEntity($existingPayment);
    }
}
