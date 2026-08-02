<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

abstract class BackfillCommand extends Command
{
    /**
     * @param  callable(bool, int): array<string, mixed>  $runner
     * @param  callable(array<string, mixed>): int  $blockingIssueCounter
     * @param  list<string>  $expandTables
     */
    protected function executeBackfill(
        callable $runner,
        callable $blockingIssueCounter,
        array $expandTables,
    ): int {
        $phase = strtolower(trim((string) $this->option('phase')));
        if (! in_array($phase, ['expand', 'backfill', 'validate', 'contract'], true)) {
            return $this->failWithPayload([
                'status' => 'failed',
                'code' => 'invalid_phase',
                'message' => 'phase must be expand, backfill, validate, or contract.',
            ]);
        }

        $limit = $this->integerOption('limit', 1000, minimum: 1);
        if ($limit === null) {
            return $this->failWithPayload([
                'status' => 'failed',
                'code' => 'invalid_limit',
                'message' => 'limit must be a positive bounded integer.',
            ]);
        }

        $maxBlockingGaps = $this->integerOption('max-blocking-gaps', 0, minimum: 0);
        if ($maxBlockingGaps === null) {
            return $this->failWithPayload([
                'status' => 'failed',
                'code' => 'invalid_max_blocking_gaps',
                'message' => 'max-blocking-gaps must be a non-negative integer.',
            ]);
        }

        $dryRun = (bool) $this->option('dry-run') || $phase === 'validate';
        $legacyContract = [
            'status' => 'blocked',
            'legacy_fields_and_tables_preserved' => true,
            'reason' => 'No contract is allowed before verified consumers and ratchets exist.',
        ];

        if ($phase === 'contract') {
            $payload = $this->payload(
                phase: $phase,
                dryRun: true,
                limit: $limit,
                maxBlockingGaps: $maxBlockingGaps,
                status: 'blocked',
                blockingIssueCount: 1,
                data: ['legacy_contract' => $legacyContract],
                legacyContract: $legacyContract,
            );

            return $this->emitPayload($payload, exitCode: 5);
        }

        if ($phase === 'expand') {
            $structures = [];
            foreach ($expandTables as $table) {
                $structures[$table] = Schema::hasTable($table);
            }

            $missing = array_keys(array_filter($structures, static fn (bool $exists): bool => ! $exists));
            $payload = $this->payload(
                phase: $phase,
                dryRun: true,
                limit: $limit,
                maxBlockingGaps: $maxBlockingGaps,
                status: $missing === [] ? 'proved' : 'failed',
                blockingIssueCount: count($missing),
                data: [
                    'structures' => $structures,
                    'missing_structures' => $missing,
                ],
                legacyContract: $legacyContract,
            );

            return $this->emitPayload($payload, exitCode: $missing === [] ? self::SUCCESS : 6);
        }

        try {
            $data = $runner($dryRun, $limit);
        } catch (\Throwable $exception) {
            return $this->failWithPayload([
                'status' => 'failed',
                'code' => 'backfill_exception',
                'message' => $exception->getMessage(),
            ]);
        }

        $blockingIssueCount = $blockingIssueCounter($data);
        $status = $blockingIssueCount > $maxBlockingGaps ? 'failed' : 'proved';
        $payload = $this->payload(
            phase: $phase,
            dryRun: $dryRun,
            limit: $limit,
            maxBlockingGaps: $maxBlockingGaps,
            status: $status,
            blockingIssueCount: $blockingIssueCount,
            data: $data,
            legacyContract: $legacyContract,
        );

        return $this->emitPayload(
            $payload,
            exitCode: $status === 'proved' ? self::SUCCESS : 7,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $legacyContract
     * @return array<string, mixed>
     */
    private function payload(
        string $phase,
        bool $dryRun,
        int $limit,
        int $maxBlockingGaps,
        string $status,
        int $blockingIssueCount,
        array $data,
        array $legacyContract,
    ): array {
        $payload = [
            'schema' => 1,
            'command' => $this->getName(),
            'phase' => $phase,
            'dry_run' => $dryRun,
            'limit' => $limit,
            'max_blocking_gaps' => $maxBlockingGaps,
            'status' => $status,
            'blocking_issue_count' => $blockingIssueCount,
            'data' => $data,
            'rollback' => [
                'dry_run_rolls_back' => $dryRun,
                'legacy_structures_changed' => false,
                'new_structures_only' => true,
            ],
            'legacy_contract' => $legacyContract,
        ];
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $payload['checkpoint'] = [
            'schema' => 1,
            'command' => $this->getName(),
            'phase' => $phase,
            'limit' => $limit,
            'blocking_issue_count' => $blockingIssueCount,
            'report_sha256' => hash('sha256', $canonical),
        ];

        return $payload;
    }

    /** @return int|null */
    private function integerOption(string $name, int $default, int $minimum): ?int
    {
        $value = $this->option($name);
        $value = $value === null || $value === '' ? (string) $default : (string) $value;

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $integer = (int) $value;

        return $integer >= $minimum ? $integer : null;
    }

    /** @param array<string, mixed> $payload */
    private function emitPayload(array $payload, int $exitCode): int
    {
        $reportPath = $this->option('report');
        if (is_string($reportPath) && trim($reportPath) !== '') {
            $this->writeJson($reportPath, $payload);
        }

        $checkpointPath = $this->option('checkpoint');
        if (is_string($checkpointPath) && trim($checkpointPath) !== '') {
            $this->writeJson($checkpointPath, $payload['checkpoint']);
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ((bool) $this->option('json')) {
            $this->line($encoded);
        } else {
            $this->line('status: '.$payload['status']);
            $this->line('blocking_issue_count: '.$payload['blocking_issue_count']);
            $this->line('phase: '.$payload['phase']);
        }

        return $exitCode;
    }

    /** @param array<string, mixed> $payload */
    private function failWithPayload(array $payload): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->error((string) ($payload['message'] ?? $payload['code'] ?? 'Backfill command failed.'));
        }

        return self::INVALID;
    }

    /** @param array<string, mixed> $payload */
    private function writeJson(string $path, array $payload): void
    {
        $resolved = $this->resolvePath($path);
        $directory = dirname($resolved);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create report directory: '.$directory);
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
        if (file_put_contents($resolved, $encoded, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write report: '.$resolved);
        }
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
