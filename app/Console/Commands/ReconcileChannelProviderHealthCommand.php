<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Channels\ChannelProviderHealthReconciler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

final class ReconcileChannelProviderHealthCommand extends Command
{
    protected $signature = 'dovra:channels-reconcile-provider-health
    {--tenant= : Required tenant UUID}
    {--limit=500 : Maximum stale transitions selected per run}
    {--dry-run : Report without updating health rows}
    {--json : Emit the closed machine-readable report}';

    protected $description = 'Reconcile stale channel provider health for one tenant';

    public function __construct(private readonly ChannelProviderHealthReconciler $reconciler)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $json = (bool) $this->option('json');

        try {
            $tenant = $this->option('tenant');
            $limit = $this->parseLimit($this->option('limit'));
            if (! is_string($tenant) || trim($tenant) === '' || $limit === null) {
                throw new \InvalidArgumentException('invalid request');
            }

            $report = $this->reconciler->reconcile(
                tenantId: $tenant,
                observedAt: CarbonImmutable::now()->utc()->startOfSecond(),
                limit: $limit,
                dryRun: (bool) $this->option('dry-run'),
            );

            if ($json) {
                $this->output->write(json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
            } else {
                $counts = $report['counts'];
                $this->output->writeln(sprintf(
                    'status=%s mode=%s eligible=%d candidates=%d selected=%d transitioned=%d skipped-race=%d batch-complete=%s',
                    $report['status'],
                    $report['mode'],
                    $counts['eligible_connections'],
                    $counts['transition_candidates'],
                    $counts['selected_candidates'],
                    $counts['transitioned'],
                    $counts['skipped_race'],
                    $report['batch_complete'] ? 'true' : 'false',
                ));
            }

            return $report['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            if ($json) {
                $this->output->write(json_encode([
                    'schema' => 'dovra.channel-provider-health-reconciliation.v1',
                    'status' => 'invalid',
                    'code' => 'invalid_request',
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
            } else {
                $this->output->writeln('Channel provider health reconciliation request is invalid.');
            }

            return self::INVALID;
        }
    }

    private function parseLimit(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/\A[0-9]+\z/D', $value) !== 1) {
            return null;
        }

        $limit = (int) $value;

        return $limit >= 1 && $limit <= 1000 ? $limit : null;
    }
}
