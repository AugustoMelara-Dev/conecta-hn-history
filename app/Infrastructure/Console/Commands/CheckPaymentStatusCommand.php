<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * CheckPaymentStatusCommand - Diagnostic tool for payment storage state.
 */
class CheckPaymentStatusCommand extends Command
{
    protected $signature = 'billing:check-status {payment_id : The ID of the payment to inspect}';

    protected $description = 'Diagnose the distributed state of a payment (DB vs temp storage vs permanent storage)';

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $paymentId = (string) $this->argument('payment_id');
        $this->info("Diagnosing Payment: {$paymentId}");

        $payment = $this->paymentRepository->findById($paymentId);

        if ($payment === null) {
            $this->error('Payment record not found in database.');

            return self::FAILURE;
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

        $currentPath = $payment->proofUrl;
        $disk = (string) config('filesystems.default', 's3');
        $storage = Storage::disk($disk);
        $expectedTempPath = "temp/{$paymentId}.pdf";

        $existsStored = $currentPath !== null
            && $currentPath !== ''
            && $storage->exists($currentPath);
        $existsTempCandidate = $storage->exists($expectedTempPath);
        $isPointingToTemp = $currentPath !== null
            && $currentPath !== ''
            && str_starts_with($currentPath, 'temp');

        $this->info("\nStorage State Analysis (Disk: {$disk}):");

        $rows = [];
        $rows[] = ['Stored Path Reconciled?', $existsStored ? 'YES' : 'NO (Broken Link)'];
        $rows[] = ['DB Points to Temp?', $isPointingToTemp ? 'YES' : 'NO'];
        $rows[] = ["Orphan in {$expectedTempPath}?", $existsTempCandidate ? 'YES' : 'NO'];

        $this->table(['Check', 'Result'], $rows);

        $this->info("\nVerdict:");

        if ($isPointingToTemp) {
            if ($existsStored) {
                $this->warn('STATUS: STUCK IN QUARANTINE.');
                $this->info("RECOMMENDATION: Run `php artisan billing:force-promote {$paymentId}`");
            } else {
                $this->error('STATUS: DATA LOSS / EXPIRED.');
                $this->warn("ACTION: Check if {$expectedTempPath} or a permanent backup exists manually.");
            }
        } elseif (! $existsStored && $currentPath !== null && $currentPath !== '') {
            $this->error('STATUS: BROKEN PERMANENT LINK.');
        } elseif ($existsStored) {
            $this->info('STATUS: HEALTHY.');
        } else {
            $this->warn('STATUS: INDETERMINATE.');
        }

        return self::SUCCESS;
    }
}
