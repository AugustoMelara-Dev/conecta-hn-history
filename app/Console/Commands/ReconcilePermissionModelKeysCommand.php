<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Identity\Permissions\PermissionModelKeyMigration;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class ReconcilePermissionModelKeysCommand extends Command
{
    protected $signature = 'permissions:reconcile-model-keys
        {phase? : preflight, expand, backfill, validate, or contract}
        {--phase= : Explicit phase override}
        {--batch-size=100 : Maximum assignments examined per bounded batch}
        {--checkpoint= : Atomic resumable checkpoint JSON path}
        {--artifact= : Validation artifact JSON path}
        {--expected-head= : Expected nonempty git HEAD identity for validation}
        {--expected-tree= : Expected nonempty git tree identity for validation}
        {--dry-run : Roll back shadow writes and checkpoint advancement}
        {--json : Emit the complete sanitized report as JSON}';

    protected $description = 'Preflight, expand, backfill, or validate Spatie UUID model-key pivots without cutover.';

    public function handle(PermissionModelKeyMigration $migration): int
    {
        $phaseOption = $this->option('phase');
        $phase = is_string($phaseOption) && trim($phaseOption) !== ''
            ? $phaseOption
            : ((string) ($this->argument('phase') ?: 'preflight'));
        $batchSize = $this->integerOption('batch-size', 100);
        if ($batchSize === null) {
            $this->error('batch-size must be a bounded positive integer.');

            return self::INVALID;
        }

        $checkpoint = $this->stringOption('checkpoint');
        $artifact = $this->stringOption('artifact');
        $expectedHead = $this->stringOption('expected-head');
        $expectedTree = $this->stringOption('expected-tree');
        try {
            $report = $migration->run(
                phase: $phase,
                batchSize: $batchSize,
                checkpointPath: $checkpoint,
                artifactPath: $artifact,
                dryRun: (bool) $this->option('dry-run'),
                expectedHead: $expectedHead,
                expectedTree: $expectedTree,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $payload = $report->toArray();
        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->line('phase: '.$report->phase);
            $this->line('status: '.$report->status);
            $this->line('shape: '.$report->shape);
            $this->line('verification_scope: '.$report->verificationScope);
            $this->line('examined: '.$report->examined);
            $this->line('copied: '.$report->copied);
            if ($report->blockingIssues !== []) {
                $this->line('blocking_issues: '.implode(',', array_values(array_unique($report->blockingIssues))));
            }
        }

        return match ($report->status) {
            'ready', 'expanded', 'already_expanded', 'canonical_noop', 'pending', 'complete', 'valid', 'dry_run' => self::SUCCESS,
            'blocked' => 5,
            default => self::FAILURE,
        };
    }

    private function integerOption(string $name, int $default): ?int
    {
        $raw = $this->option($name);
        $raw = $raw === null || $raw === '' ? (string) $default : (string) $raw;
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        $value = (int) $raw;

        return $value > 0 && $value <= 10000 ? $value : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
