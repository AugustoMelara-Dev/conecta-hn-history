<?php

declare(strict_types=1);

namespace App\Application\Reporting\Commands;

use App\Application\Reporting\ReportExecutor;
use App\Application\Reporting\ReportRegistry;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use Illuminate\Console\Command;

class RunReportCommand extends Command
{
    protected $signature = 'reports:run {key} {--tenant=} {--range=30d}';

    protected $description = 'Run a specific report and display summary';

    public function handle(ReportRegistry $registry, ReportExecutor $executor): int
    {
        $key = ReportKey::tryFrom($this->argument('key'));
        if ($key === null || ! $registry->has($key)) {
            $this->error("Report not found: {$this->argument('key')}");

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant');
        if (! is_string($tenantId) || trim($tenantId) === '') {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }
        $range = ReportRange::fromPreset($this->option('range'));
        $data = $executor->execute($key, $tenantId, $range);

        $this->info("Report: {$key->label()}");
        $this->info('Rows: '.count($data->rows));
        $this->info('Series: '.count($data->series));
        if (($data->metadata['status'] ?? null) === 'unavailable') {
            $this->warn('Unavailable: '.($data->metadata['reason'] ?? 'required source data is missing'));
        }

        return self::SUCCESS;
    }
}
