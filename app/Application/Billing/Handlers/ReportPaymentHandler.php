<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Results\ReportPaymentResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Events\PaymentReported;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ReportPaymentHandler - Command Handler for Manual Payment Reporting
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ENTITY HYDRATION: Handler is responsible for constructing domain objects║
 * ║  TYPE SAFE: Uses Money VO and string UUIDs throughout.                   ║
 * ║  HEXAGONAL: No Eloquent or Infrastructure leakage.                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class ReportPaymentHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly DocumentStorageServiceInterface $documentStorage,
        private readonly TransactionalEventDispatcher $eventDispatcher,
    ) {}

    /**
     * Handle the payment reporting command.
     *
     * @param ReportPaymentCommand $command
     * @return ReportPaymentResult
     */
    public function handle(ReportPaymentCommand $command): ReportPaymentResult
    {
        // ═══════════════════════════════════════════════════════════════════
        // STEP 1: FILE STORAGE (Delegate to Infrastructure)
        // ═══════════════════════════════════════════════════════════════════
        $receiptPath = null;
        if ($command->proofOfPaymentPath !== null) {
            $receiptPath = $this->documentStorage->storeProofOfPayment(
                $command->proofOfPaymentPath,
                $command->paymentReference
            );
        }

        // ═══════════════════════════════════════════════════════════════════
        // STEP 2: ENTITY HYDRATION (Handler Responsibility)
        // ═══════════════════════════════════════════════════════════════════
        
        // 2a. Generate unique identity (UUID v4)
        $paymentId = Str::uuid()->toString();

        // 2b. Construct Money Value Object
        $amount = Money::of($command->amountInCents, $command->currency);

        // 2c. Assemble PaymentEntity (Pure Domain Object)
        $paymentEntity = new PaymentEntity(
            id: $paymentId,
            tenantId: (string) $command->tenantId,
            amount: $amount,
            bank: $command->bank,
            referenceNumber: $command->paymentReference,
            status: PaymentStatus::PENDING,
            proofUrl: $receiptPath,
            createdAt: new DateTimeImmutable(),
        );

        // ═══════════════════════════════════════════════════════════════════
        // STEP 3: PERSIST VIA REPOSITORY
        // ═══════════════════════════════════════════════════════════════════
        $this->paymentRepository->save($paymentEntity);

        // ═══════════════════════════════════════════════════════════════════
        // STEP 4: DISPATCH DOMAIN EVENT (Transactional Outbox)
        // ═══════════════════════════════════════════════════════════════════
        $this->eventDispatcher->dispatch(new PaymentReported(
            paymentId: $paymentEntity->id,
            tenantId: $command->tenantId,
            amountInCents: $command->amountInCents,
            currency: $command->currency,
            reference: $command->paymentReference,
            idempotencyKey: $command->idempotencyKey,
        ));

        // ═══════════════════════════════════════════════════════════════════
        // STEP 5: AUDIT LOG (PII Scrubbed)
        // ═══════════════════════════════════════════════════════════════════
        Log::info('Payment reported successfully', $this->sanitizePayload([
            'payment_id' => $paymentEntity->id,
            'tenant_id' => $command->tenantId,
            'reference' => $command->paymentReference,
            'amount_cents' => $command->amountInCents,
            'currency' => $command->currency,
        ]));

        // ═══════════════════════════════════════════════════════════════════
        // STEP 6: RETURN RESULT DTO
        // ═══════════════════════════════════════════════════════════════════
        return ReportPaymentResult::fromEntity($paymentEntity);
    }
}
