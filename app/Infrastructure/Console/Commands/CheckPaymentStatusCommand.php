<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * CheckPaymentStatusCommand - The Diagnostic Tool (Level 21 - Config Safe)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OPERATIONAL VISIBILITY: CONFIG SAFE                                     ║
 * ║  1. Checks Database State (Status, Path, Version).                       ║
 * ║  2. DISK AWARENESS: Uses config('filesystems.default') instead of runtime environment reads. ║
 * ║     - Crucial for proper diagnostics in production.                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class CheckPaymentStatusCommand extends Command
{
    protected $signature = 'billing:check-status {payment_id : The ID of the payment to inspect}';

    protected $description = 'Diagnose the Distributed State of a Payment (DB vs S3 Temp vs S3 Perm)';

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $paymentId = $this->argument('payment_id');
        $this->info("🔍 Diagnosing Payment: {$paymentId}");

        // 1. DB INSPECTION
        $payment = $this->paymentRepository->findById($paymentId);

        if (! $payment) {
            $this->error('❌ Payment record NOT FOUND in Database.');

            return 1;
        }

        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $payment->id],
                ['Status', $payment->status->value],
                ['Current Proof Path', $payment->proofUrl ?? 'NULL'],
                ['OCC Version', $payment->version],
                ['Created At', $payment->createdAt->format('Y-m-d H:i:s')],
            ]
        );

        // 2. STORAGE INSPECTION
        $currentPath = $payment->proofUrl;

        // Use the configured disk, default to 's3' if not specified in config
        $disk = config('filesystems.default', 's3');
        $storage = Storage::disk($disk);

        $expectedTempPath = "temp/{$paymentId}.pdf";

        $hasCurrentPath = is_string($currentPath) && $currentPath !== '';
        $existsStored = $hasCurrentPath && $storage->exists($currentPath);
        $existsTempCandidate = $storage->exists($expectedTempPath);

        $this->info("\n💾 Storage State Analysis (Disk: {$disk}):");

        $rows = [];
        $rows[] = ['Stored Path Reconciled?', $existsStored ? '✅ YES' : '❌ NO (Broken Link)'];

        $isPointingToTemp = $hasCurrentPath && str_starts_with($currentPath, 'temp');
        $rows[] = ['DB Points to Temp?', $isPointingToTemp ? '⚠️ YES' : 'NO'];

        $rows[] = ["Orphan in {$expectedTempPath}?", $existsTempCandidate ? '⚠️ YES' : 'NO'];

        $this->table(['Check', 'Result'], $rows);

        // 3. RECOMMENDATION ENGINE
        $this->info("\n🤖 AI Verdict:");

        if ($isPointingToTemp && $existsStored) {
            $this->warn('► STATUS: STUCK IN QUARANTINE.');
            $this->info("RECOMMENDATION: Run `php artisan billing:force-promote {$paymentId}`");
        } elseif ($isPointingToTemp && ! $existsStored) {
            $this->error('► STATUS: DATA LOSS / EXPIRED.');
            $this->warn("ACTION: Check if {$expectedTempPath} or permanent backup exists manually.");
        } elseif (! $existsStored && $hasCurrentPath) {
            $this->error('► STATUS: BROKEN PERMANENT LINK.');
        } elseif ($existsStored) {
            $this->info('► STATUS: HEALTHY.');
        } else {
            $this->warn('► STATUS: INDETERMINATE.');
        }

        return 0;
    }
}
