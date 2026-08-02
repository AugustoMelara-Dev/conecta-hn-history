<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class BillingGlobalAuthorityRatchetTest extends TestCase
{
    public function test_global_billing_slice_does_not_reach_legacy_payment_tables_or_legacy_credit_authorities(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = [
            $root.'/app/Application/Billing/Global',
            $root.'/app/Domain/Billing/Global',
            $root.'/app/Infrastructure/Billing/Global',
        ];
        $violations = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                $violations[] = str_replace('\\', '/', substr($path, strlen($root) + 1)).' (missing)';
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

                if (preg_match('/subscription_payments|subscription_intents|tenant_billing_references|ai_credit_reservations|ai_credit_ledger_entries|config\([\'"](?:subscriptions|billing|ai)\./i', $source) === 1) {
                    $violations[] = $relative;
                }
            }
        }

        self::assertSame([], $violations, 'Global billing must stay isolated from legacy billing/payment/AI authorities.');
    }
}
