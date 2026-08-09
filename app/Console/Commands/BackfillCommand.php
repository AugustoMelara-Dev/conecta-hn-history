<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

abstract class BackfillCommand extends Command
{
    private const MAX_BATCH_LIMIT = 5_000;

    private const MAX_SOURCE_OFFSET = 2_147_483_647;

    /**
     * @param  callable(bool, int, int): array<string, mixed>  $runner
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

        $limit = $this->integerOption('limit', 1000, minimum: 1, maximum: self::MAX_BATCH_LIMIT);
        if ($limit === null) {
            return $this->failWithPayload([
                'status' => 'failed',
                'code' => 'invalid_limit',
                'message' => 'limit must be a positive bounded integer.',
            ]);
        }

        $offset = $this->integerOption('offset', 0, minimum: 0, maximum: self::MAX_SOURCE_OFFSET);
        if ($offset === null) {
            return $this->failWithPayload([
                'status' => 'failed',
                'code' => 'invalid_offset',
                'message' => 'offset must be a non-negative bounded integer.',
            ]);
        }

        if ((bool) $this->option('resume')) {
            $checkpointPath = $this->option('checkpoint');
            if (! is_string($checkpointPath) || trim($checkpointPath) === '') {
                return $this->failWithPayload([
                    'status' => 'failed',
                    'code' => 'resume_requires_checkpoint',
                    'message' => '--resume requires --checkpoint.',
                ]);
            }

            $checkpoint = $this->readJson($checkpointPath);
            if ($checkpoint === null) {
                return $this->failWithPayload([
                    'status' => 'failed',
                    'code' => 'checkpoint_not_resumable',
                    'message' => 'The checkpoint is missing or invalid JSON.',
                ]);
            }

            $checkpointError = $this->checkpointError($checkpoint, $phase, $limit);
            if ($checkpointError !== null) {
                return $this->failWithPayload([
                    'status' => 'failed',
                    'code' => 'checkpoint_not_resumable',
                    'message' => $checkpointError,
                ]);
            }

            $requestedOffset = $this->integerOption('offset', 0, minimum: 0, maximum: self::MAX_SOURCE_OFFSET);
            if ($requestedOffset === null) {
                return $this->failWithPayload([
                    'status' => 'failed',
                    'code' => 'invalid_offset',
                    'message' => 'offset must be a non-negative bounded integer.',
                ]);
            }
            if ($requestedOffset !== 0 && $requestedOffset !== (int) $checkpoint['next_source_offset']) {
                return $this->failWithPayload([
                    'status' => 'failed',
                    'code' => 'resume_offset_conflict',
                    'message' => 'An explicit offset must equal the checkpoint next source offset.',
                ]);
            }

            $checkpointOffset = filter_var(
                $checkpoint['next_source_offset'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0]],
            );
            if ($checkpointOffset === false) {
                return $this->failWithPayload([
                    'status' => 'failed',
                    'code' => 'checkpoint_offset_invalid',
                    'message' => 'The checkpoint source offset is invalid.',
                ]);
            }
            $offset = (int) $checkpointOffset;
        }

        $maxBlockingGaps = $this->integerOption('max-blocking-gaps', 0, minimum: 0, maximum: self::MAX_SOURCE_OFFSET);
        if ($maxBlockingGaps === null) {
            return $this->failWithPayload([
                'status' => 'failed',
                'code' => 'invalid_max_blocking_gaps',
                'message' => 'max-blocking-gaps must be a non-negative integer.',
            ]);
        }

        if ($offset > self::MAX_SOURCE_OFFSET - $limit) {
            return $this->failWithPayload([
                'status' => 'failed',
                'code' => 'source_window_overflow',
                'message' => 'offset plus limit must remain within the bounded source window.',
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
                sourceOffset: $offset,
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
                sourceOffset: $offset,
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
            $data = $runner($dryRun, $limit, $offset);
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
            sourceOffset: $offset,
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
        int $sourceOffset,
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
            'source_offset' => $sourceOffset,
            'next_source_offset' => $sourceOffset + $limit,
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
            'source_offset' => $sourceOffset,
            'next_source_offset' => $sourceOffset + $limit,
            'max_blocking_gaps' => $maxBlockingGaps,
            'status' => $status,
            'blocking_issue_count' => $blockingIssueCount,
            'report_sha256' => hash('sha256', $canonical),
        ];
        $payload['checkpoint']['checkpoint_sha256'] = $this->checkpointChecksum($payload['checkpoint']);

        return $payload;
    }

    private function integerOption(string $name, int $default, int $minimum, int $maximum): ?int
    {
        $value = $this->option($name);
        $value = $value === null || $value === '' ? (string) $default : (string) $value;

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $integer = (int) $value;

        return $integer >= $minimum && $integer <= $maximum ? $integer : null;
    }

    /** @param array<string, mixed> $payload */
    private function emitPayload(array $payload, int $exitCode): int
    {
        $reportPath = $this->option('report');
        if (is_string($reportPath) && trim($reportPath) !== '') {
            $this->writeJson($reportPath, $payload);
        }

        $checkpointPath = $this->option('checkpoint');
        if ($exitCode === self::SUCCESS
            && is_string($checkpointPath)
            && trim($checkpointPath) !== '') {
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
        $temporary = $resolved.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($temporary, $encoded, LOCK_EX) === false || ! rename($temporary, $resolved)) {
            @unlink($temporary);
            throw new \RuntimeException('Unable to atomically write report: '.$resolved);
        }
    }

    /** @param array<string, mixed> $checkpoint */
    private function checkpointError(array $checkpoint, string $phase, int $limit): ?string
    {
        if (($checkpoint['schema'] ?? null) !== 1
            || ($checkpoint['command'] ?? null) !== $this->getName()
            || ($checkpoint['phase'] ?? null) !== $phase
            || ($checkpoint['status'] ?? null) !== 'proved') {
            return 'The checkpoint command, phase, schema, or status does not match this resumable run.';
        }

        foreach (['limit', 'source_offset', 'next_source_offset', 'max_blocking_gaps', 'blocking_issue_count'] as $key) {
            if (! is_int($checkpoint[$key] ?? null) || $checkpoint[$key] < 0) {
                return 'The checkpoint contains an invalid numeric progression field.';
            }
        }
        $maxBlockingGaps = $this->integerOption('max-blocking-gaps', 0, minimum: 0, maximum: self::MAX_SOURCE_OFFSET);
        if ($maxBlockingGaps === null) {
            return 'The current max-blocking-gaps option is invalid.';
        }
        if ($checkpoint['limit'] !== $limit
            || $checkpoint['max_blocking_gaps'] !== $maxBlockingGaps
            || $checkpoint['source_offset'] > self::MAX_SOURCE_OFFSET
            || $checkpoint['next_source_offset'] > self::MAX_SOURCE_OFFSET
            || $checkpoint['source_offset'] > self::MAX_SOURCE_OFFSET - $checkpoint['limit']
            || $checkpoint['next_source_offset'] !== $checkpoint['source_offset'] + $checkpoint['limit']) {
            return 'The checkpoint limit or source progression does not match this run.';
        }
        if (! is_string($checkpoint['report_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $checkpoint['report_sha256']) !== 1) {
            return 'The checkpoint report digest is invalid.';
        }
        if (! is_string($checkpoint['checkpoint_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $checkpoint['checkpoint_sha256']) !== 1
            || ! hash_equals($checkpoint['checkpoint_sha256'], $this->checkpointChecksum($checkpoint))) {
            return 'The checkpoint integrity checksum is invalid.';
        }

        $reportPath = $this->option('report');
        if (is_string($reportPath) && trim($reportPath) !== '') {
            if (! is_file($this->resolvePath($reportPath))) {
                return 'The report bound to this checkpoint is missing.';
            }
            $report = $this->readJson($reportPath);
            if ($report === null) {
                return 'The report bound to this checkpoint is invalid JSON.';
            }
            unset($report['checkpoint']);
            $reportDigest = hash(
                'sha256',
                json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
            if (! hash_equals($checkpoint['report_sha256'], $reportDigest)) {
                return 'The checkpoint report digest does not match the report file.';
            }
        }

        return null;
    }

    /** @param array<string, mixed> $checkpoint */
    private function checkpointChecksum(array $checkpoint): string
    {
        unset($checkpoint['checkpoint_sha256']);

        return hash('sha256', json_encode($checkpoint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed>|null */
    private function readJson(string $path): ?array
    {
        $resolved = $this->resolvePath($path);
        if (! is_file($resolved)) {
            return null;
        }

        $contents = file_get_contents($resolved);
        if (! is_string($contents)) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
