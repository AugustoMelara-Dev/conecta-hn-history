<?php

declare(strict_types=1);

namespace App\Application\Billing\Handlers;

use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Results\ReportPaymentResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * ReportPaymentHandler - Command Handler for Manual Payment Reporting
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE: DIP + Hexagonal + Transactional Outbox       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO Storage:: facade usage (delegated to DocumentStorageService)       ║
 * ║  - NO App\Models imports (uses Repository interfaces only)              ║
 * ║  - NO DB::transaction (handled by Bus Middleware)                        ║
 * ║  - Events dispatched via TransactionalEventDispatcher (Outbox pattern)  ║
 * ║  - STRICT RETURN: ReportPaymentResult DTO (no object leak)              ║
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
        $receiptPath = null;

        // 1. Delegate File Storage (if proof exists)
        if ($command->proofOfPaymentPath !== null) {
            $receiptPath = $this->documentStorage->storeProofOfPayment(
                $command->proofOfPaymentPath,
                $command->paymentReference
            );
        }

        // 2. Persist Payment via Repository
        $payment = $this->paymentRepository->create([
            'tenant_id' => $command->tenantId,
            'bank' => $command->bank,
            'reference_number' => $command->paymentReference,
            'amount' => $command->amount,
            'receipt_path' => $receiptPath,
            'status' => 'pending', // Admin will verify
        ]);

        // 3. Dispatch Domain Event (Transactional Outbox)
        $this->eventDispatcher->dispatch(new \App\Domain\Billing\Events\PaymentReported(
            paymentId: $payment->id,
            tenantId: $command->tenantId,
            amount: $command->amount,
            reference: $command->paymentReference,
        ));

        // 4. Audit Log (PII Scrubbed)
        Log::info('Payment reported successfully', $this->sanitizePayload([
            'payment_id' => $payment->id,
            'tenant_id' => $command->tenantId,
            'reference' => $command->paymentReference,
            'amount' => $command->amount,
        ]));

        return ReportPaymentResult::fromEntity($payment);
    }
}

