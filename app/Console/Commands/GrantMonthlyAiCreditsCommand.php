<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Services\MonthlyAiCreditGrantService;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class GrantMonthlyAiCreditsCommand extends Command
{
    protected $signature = 'ai:grant-monthly-credits
        {--tenant= : Grant only one tenant UUID}
        {--period= : UTC billing period in YYYY-MM format}
        {--chunk=200 : Tenants processed per chunk}';

    protected $description = 'Idempotently reset each tenant AI balance to its plan allowance for a UTC billing period.';

    public function handle(MonthlyAiCreditGrantService $grants): int
    {
        $periodOption = $this->option('period');
        if ($periodOption !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $periodOption)) {
            $this->error('The --period option must use YYYY-MM.');

            return self::INVALID;
        }

        $period = $periodOption === null
            ? now()->utc()
            : Carbon::createFromFormat('!Y-m', (string) $periodOption, 'UTC');
        $tenantId = trim((string) $this->option('tenant'));
        $chunk = max(1, min(1000, (int) $this->option('chunk')));
        $granted = 0;
        $skipped = 0;

        $query = Tenant::query()->orderBy('id');
        if ($tenantId !== '') {
            $query->whereKey($tenantId);
        }

        $query->chunkById($chunk, function ($tenants) use ($grants, $period, &$granted, &$skipped): void {
            foreach ($tenants as $tenant) {
                $grants->grant($tenant, $period) ? $granted++ : $skipped++;
            }
        }, 'id');

        $this->info("AI monthly credits: {$granted} granted; {$skipped} already granted.");

        return self::SUCCESS;
    }
}
