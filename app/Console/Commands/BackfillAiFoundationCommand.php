<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Ai\Conversation\AiFoundationBackfill;
use Illuminate\Console\Command;

final class BackfillAiFoundationCommand extends Command
{
    protected $signature = 'dovra:ai-foundation-backfill
        {--limit=500 : Maximum number of executions/conversations to inspect}
        {--phase=backfill : expand, backfill, validate, or contract}
        {--cursor= : Opaque execution cursor returned by the previous batch}
        {--conversation-cursor= : Opaque conversation cursor returned by the previous batch}
        {--checkpoint= : JSON checkpoint file to read and update atomically}
        {--dry-run : Validate the batch without mutating data}
        {--json : Emit a machine-readable report}';

    protected $description = 'Backfill canonical AI foundation lineage, retention defaults, and conversation links.';

    public function handle(AiFoundationBackfill $backfill): int
    {
        $phase = (string) $this->option('phase');
        $cursor = $this->option('cursor');
        $conversationCursor = $this->option('conversation-cursor');
        $checkpoint = $this->option('checkpoint');
        $previousChain = hash('sha256', 'dovra-ai-foundation|'.$phase.'|genesis');
        $previousBatch = 0;
        $executionSnapshot = null;
        $conversationSnapshot = null;
        $previousCumulative = [
            'gaps' => 0,
            'conflicts' => 0,
            'legacy_unresolved' => 0,
        ];

        if (is_string($checkpoint) && trim($checkpoint) !== '' && is_file($this->resolvePath($checkpoint))) {
            $resolvedCheckpoint = $this->resolvePath($checkpoint);
            $contents = file_get_contents($resolvedCheckpoint);
            $decoded = is_string($contents) ? json_decode($contents, true) : null;
            if (! is_array($decoded)) {
                return $this->failWithPayload('checkpoint_invalid_json', 'The AI foundation checkpoint is invalid JSON.');
            }

            $checkpointError = $this->checkpointError($decoded, $phase, max(1, (int) $this->option('limit')));
            if ($checkpointError !== null) {
                return $this->failWithPayload('checkpoint_not_resumable', $checkpointError);
            }

            $checkpointCursor = is_string($decoded['next_cursor'] ?? null) ? $decoded['next_cursor'] : null;
            $checkpointConversationCursor = is_string($decoded['conversation_next_cursor'] ?? null)
                ? $decoded['conversation_next_cursor']
                : null;
            if ($cursor !== null && $cursor !== $checkpointCursor) {
                return $this->failWithPayload('cursor_checkpoint_conflict', 'The explicit execution cursor conflicts with the checkpoint.');
            }
            if ($conversationCursor !== null && $conversationCursor !== $checkpointConversationCursor) {
                return $this->failWithPayload('conversation_cursor_checkpoint_conflict', 'The explicit conversation cursor conflicts with the checkpoint.');
            }
            $cursor ??= $checkpointCursor;
            $conversationCursor ??= $checkpointConversationCursor;
            $previousChain = (string) $decoded['validation_chain'];
            $previousBatch = (int) $decoded['batch_sequence'];
            $sourceSnapshot = is_array($decoded['source_snapshot'] ?? null)
                ? $decoded['source_snapshot']
                : null;
            $executionSnapshot = $sourceSnapshot['executions'] ?? null;
            $conversationSnapshot = $sourceSnapshot['conversations'] ?? null;
            $previousCumulative = [
                'gaps' => (int) $decoded['cumulative_gaps'],
                'conflicts' => (int) $decoded['cumulative_conflicts'],
                'legacy_unresolved' => (int) $decoded['cumulative_legacy_unresolved'],
            ];
        }

        $report = $backfill->run(
            limit: max(1, (int) $this->option('limit')),
            cursor: is_string($cursor) ? $cursor : null,
            phase: $phase,
            dryRun: (bool) $this->option('dry-run'),
            conversationCursor: is_string($conversationCursor) ? $conversationCursor : null,
            executionSnapshot: $executionSnapshot,
            conversationSnapshot: $conversationSnapshot,
        )->toArray();

        $report['batch_sequence'] = $previousBatch + 1;
        foreach (array_keys($previousCumulative) as $key) {
            $report['cumulative_'.$key] = $previousCumulative[$key] + (int) ($report[$key] ?? 0);
        }
        if ($phase === 'validate'
            && (bool) ($report['validated'] ?? false)
            && ($report['cumulative_gaps'] > 0
                || $report['cumulative_conflicts'] > 0
                || $report['cumulative_legacy_unresolved'] > 0)) {
            $report['validated'] = false;
        }
        $report['validation_chain'] = hash('sha256', implode('|', [
            $previousChain,
            (string) ($report['phase'] ?? $phase),
            (string) ($report['batch_checksum'] ?? ''),
            (string) ($report['next_cursor'] ?? ''),
            (string) ($report['conversation_next_cursor'] ?? ''),
            (string) ((bool) ($report['has_more'] ?? false) ? 'more' : 'exhausted'),
            (string) $report['cumulative_gaps'],
            (string) $report['cumulative_conflicts'],
            (string) $report['cumulative_legacy_unresolved'],
        ]));
        $report['checkpoint_status'] = (bool) ($report['has_more'] ?? false)
            ? 'partial'
            : (($phase === 'validate' && (bool) ($report['validated'] ?? false)) ? 'validated' : 'exhausted');

        if ($phase !== 'contract' && is_string($checkpoint) && trim($checkpoint) !== '') {
            $resolvedCheckpoint = $this->resolvePath($checkpoint);
            $directory = dirname($resolvedCheckpoint);
            if (! is_dir($directory)) {
                $this->error('Checkpoint directory does not exist: '.$directory);

                return self::FAILURE;
            }
            $checkpointPayload = [
                'schema' => 1,
                'command' => $this->getName(),
                'phase' => $phase,
                'limit' => max(1, (int) $this->option('limit')),
                'batch_sequence' => $report['batch_sequence'],
                'checkpoint_status' => $report['checkpoint_status'],
                'next_cursor' => $report['next_cursor'],
                'conversation_next_cursor' => $report['conversation_next_cursor'],
                'batch_checksum' => $report['batch_checksum'],
                'source_snapshot' => $report['source_snapshot'] ?? null,
                'cumulative_gaps' => $report['cumulative_gaps'],
                'cumulative_conflicts' => $report['cumulative_conflicts'],
                'cumulative_legacy_unresolved' => $report['cumulative_legacy_unresolved'],
                'has_more' => $report['has_more'],
                'validated' => $report['validated'],
                'validation_chain' => $report['validation_chain'],
            ];
            $checkpointPayload['checkpoint_sha256'] = $this->checkpointChecksum($checkpointPayload);
            $temporary = $resolvedCheckpoint.'.'.bin2hex(random_bytes(6)).'.tmp';
            if (file_put_contents($temporary, json_encode($checkpointPayload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false
                || ! rename($temporary, $resolvedCheckpoint)) {
                @unlink($temporary);
                $this->error('Unable to atomically update checkpoint: '.$resolvedCheckpoint);

                return self::FAILURE;
            }
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode(
                $report,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ));
        } else {
            foreach ($report as $name => $count) {
                $value = is_scalar($count) || $count === null
                    ? (string) $count
                    : json_encode($count, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $this->line($name.': '.$value);
            }
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $checkpoint */
    private function checkpointError(array $checkpoint, string $phase, int $limit): ?string
    {
        if (($checkpoint['schema'] ?? null) !== 1
            || ($checkpoint['command'] ?? null) !== $this->getName()
            || ($checkpoint['phase'] ?? null) !== $phase
            || ! in_array($checkpoint['checkpoint_status'] ?? null, ['partial', 'exhausted', 'validated'], true)) {
            return 'The checkpoint command, phase, schema, or status does not match this run.';
        }
        if (! is_int($checkpoint['limit'] ?? null)
            || $checkpoint['limit'] !== $limit
            || ! is_int($checkpoint['batch_sequence'] ?? null)
            || $checkpoint['batch_sequence'] < 1
            || ! is_string($checkpoint['validation_chain'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $checkpoint['validation_chain']) !== 1
            || ! is_string($checkpoint['checkpoint_sha256'] ?? null)
            || $this->checkpointChecksum($checkpoint) !== $checkpoint['checkpoint_sha256']) {
            return 'The checkpoint lineage or integrity checksum is invalid.';
        }
        if (! is_string($checkpoint['batch_checksum'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $checkpoint['batch_checksum']) !== 1
            || ! is_bool($checkpoint['has_more'] ?? null)
            || ! is_bool($checkpoint['validated'] ?? null)
            || ! is_int($checkpoint['cumulative_gaps'] ?? null)
            || $checkpoint['cumulative_gaps'] < 0
            || ! is_int($checkpoint['cumulative_conflicts'] ?? null)
            || $checkpoint['cumulative_conflicts'] < 0
            || ! is_int($checkpoint['cumulative_legacy_unresolved'] ?? null)
            || $checkpoint['cumulative_legacy_unresolved'] < 0) {
            return 'The checkpoint batch validation fields are invalid.';
        }
        $snapshots = $checkpoint['source_snapshot'] ?? null;
        if ($snapshots !== null
            && (! is_array($snapshots)
                || ! array_key_exists('executions', $snapshots)
                || ! array_key_exists('conversations', $snapshots)
                || ! $this->validSnapshot($snapshots['executions'])
                || ! $this->validSnapshot($snapshots['conversations']))) {
            return 'The checkpoint source snapshots are invalid.';
        }
        if ($checkpoint['checkpoint_status'] === 'partial' && ! $checkpoint['has_more']) {
            return 'A partial checkpoint must report remaining work.';
        }
        if ($checkpoint['checkpoint_status'] === 'validated' && (! $checkpoint['validated'] || $checkpoint['has_more'])) {
            return 'A validated checkpoint must be exhausted and validated.';
        }
        if ($checkpoint['checkpoint_status'] !== 'validated' && $phase === 'validate' && $checkpoint['validated']) {
            return 'A validate checkpoint marked validated must use validated status.';
        }
        foreach (['next_cursor', 'conversation_next_cursor'] as $key) {
            if ($checkpoint[$key] !== null && ! is_string($checkpoint[$key])) {
                return 'The checkpoint cursor fields are invalid.';
            }
        }
        if ($limit < 1) {
            return 'The batch limit is invalid.';
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function checkpointChecksum(array $payload): string
    {
        unset($payload['checkpoint_sha256']);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param mixed $snapshot */
    private function validSnapshot(mixed $snapshot): bool
    {
        if ($snapshot === null) {
            return true;
        }
        if (! is_array($snapshot)
            || ! is_bool($snapshot['empty'] ?? false)
            || ! is_string($snapshot['created_at'] ?? null)
            || ! is_string($snapshot['id'] ?? null)) {
            return false;
        }

        return ($snapshot['empty'] ?? false) === true
            ? $snapshot['created_at'] === '' && $snapshot['id'] === ''
            : $snapshot['created_at'] !== '' && $snapshot['id'] !== '';
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function failWithPayload(string $code, string $message): int
    {
        $payload = ['status' => 'failed', 'code' => $code, 'message' => $message];
        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
