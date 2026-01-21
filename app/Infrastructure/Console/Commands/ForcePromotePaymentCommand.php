<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Shared\Exceptions\StaleObjectException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * ForcePromotePaymentCommand - The Surgical Scalpel (Level 24 - Inference Engine)
 * 
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPERATIONAL SURVIVABILITY: PATH INFERENCE                               ║
 * ║  1. State Assessment (Don't promote if already there).                   ║
 * ║  2. DISK AWARENESS: Uses config('filesystems.default').                  ║
 * ║  3. PATH INFERENCE: If Source is missing, we check Deterministic Dest.   ║
 * ║     - "temp/x.pdf" -> "permanent/x.pdf"                                  ║
 * ║     - Allows recovery even if a previous run crashed after File Move.    ║
 * ║  4. Atomic OCC Update WITH RETRY LOOP.                                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class ForcePromotePaymentCommand extends Command
{
    protected $signature = 'billing:force-promote {payment_id : The ID of the payment to rescue}';
    protected $description = 'Manually promote stuck evidence from quarantine to permanent storage (Surgical Recovery)';

    private const MAX_RETRIES = 3;

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private DocumentStorageServiceInterface $documentStorage,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $paymentId = $this->argument('payment_id');
        $this->info("Initializing Surgical Recovery for Payment: {$paymentId}");

        try {
            // DISK SETUP (Production Safe)
            $diskName = config('filesystems.default', 's3'); 
            $storage = Storage::disk($diskName);
            $this->info("Using Storage Disk: [{$diskName}]");

            // _________________________________________________________________
            // PHASE 1: PREPARATION & PROMOTION
            
            $payment = $this->paymentRepository->findById($paymentId);
            if (!$payment) {
                $this->error("Payment not found.");
                return 1;
            }

            $currentPath = $payment->proofUrl;
            if (!$currentPath) {
                 $this->error("No proof path associated with this payment.");
                 return 1;
            }
            
            $permanentPath = null;
            $actionTaken = '';

            // CASE A: DB already points to Permanent Path
            if (!str_starts_with($currentPath, 'temp')) {
                if ($storage->exists($currentPath)) {
                    $this->info("✔ Status Healthy: Path is permanent and file exists. No action needed.");
                    return 0;
                } else {
                     $this->warn("⚠ Corruption: DB says permanent, but file missing from [{$diskName}]. Cannot auto-recover.");
                     return 1;
                }
            }
            
            // CASE B: DB points to Temp Path
            // We need to decide if we promote or assume it was already promoted.
            
            if ($storage->exists($currentPath)) {
                // Normal Scenario: File is in Temp. Promote it.
                $this->info("promoting artifact from {$currentPath}...");
                $permanentPath = $this->documentStorage->promote($currentPath);
                $actionTaken = 'Promoted (Copy + Del)';
             } else {
                 // Broken Scenario: DB says Temp, but File is gone.
                 // Hypothesis: A previous run executed promote() (moved file) but failed to update DB.
                 // Test Hypothesis: Calculate deterministic permanent path.
                 
                 $inferredPerm = str_replace('temp', 'permanent', $currentPath);
                 $this->warn("⚠ Source Missing: {$currentPath}");
                 $this->info("🔍 Checking inferred destination: {$inferredPerm}");
                 
                 if ($storage->exists($inferredPerm)) {
                     // Hypothesis Confirmed. Auto-Heal.
                     $permanentPath = $inferredPerm;
                     $actionTaken = 'Inferred (Already Moved)';
                     $this->info("✔ Found file in permanent storage! Resyncing DB...");
                 } else {
                     // Hypothesis Failed. Data Loss.
                     $this->error("❌ Critical: Source file missing AND Destination file missing. Data Loss.");
                     return 1;
                 }
             }

            // _________________________________________________________________
            // PHASE 2: ATOMIC UPDATE (With Retry Protection)

            $attempts = 0;
            
            do {
                $attempts++;
                try {
                    // Refetch for Freshness
                    $freshPayment = $this->paymentRepository->findById($paymentId);
                    
                    if (!$freshPayment) {
                        $this->error("Payment deleted during recovery.");
                        return 1;
                    }

                    $fixedPayment = new PaymentEntity(
                        id: $freshPayment->id,
                        tenantId: $freshPayment->tenantId,
                        amount: $freshPayment->amount,
                        bank: $freshPayment->bank,
                        referenceNumber: $freshPayment->referenceNumber,
                        status: $freshPayment->status, 
                        proofUrl: $permanentPath, 
                        createdAt: $freshPayment->createdAt,
                        idempotencyKey: $freshPayment->idempotencyKey,
                        version: $freshPayment->version 
                    );
    
                    $this->paymentRepository->updateOptimistically($fixedPayment);
                    
                    // Success
                    $this->info("✔ Recovery Successful ({$actionTaken}).");
                    $this->info("✔ New Path: {$permanentPath}");
                    Log::info("Manual Surgical Recovery performed for Payment {$paymentId} by Admin CLI. Action: {$actionTaken}");
                    return 0;

                } catch (StaleObjectException $e) {
                    $this->warn("OCC Conflict (Attempt {$attempts}/" . self::MAX_RETRIES . "). Retrying update...");
                    usleep(500000); 
                    if ($attempts >= self::MAX_RETRIES) {
                        $this->error("❌ Failed to update DB after multiple retries. THE FILE IS MOVED TO: {$permanentPath}");
                        $this->error("MANUAL FIX REQUIRED: Update payments SET proof_url='{$permanentPath}' WHERE id='{$paymentId}'");
                        return 1;
                    }
                }
            } while ($attempts < self::MAX_RETRIES);

        } catch (Throwable $e) {
            $this->error("Recovery Failed: " . $e->getMessage());
            return 1;
        }
        
        return 1;
    }
}
