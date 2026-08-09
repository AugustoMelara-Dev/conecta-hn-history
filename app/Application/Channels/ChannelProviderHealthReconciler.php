<?php

declare(strict_types=1);

namespace App\Application\Channels;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final readonly class ChannelProviderHealthReconciler
{
    /** @return array<string, mixed> */
    public function reconcile(
        string $tenantId,
        CarbonImmutable $observedAt,
        int $limit,
        bool $dryRun,
    ): array {
        $tenantId = strtolower(trim($tenantId));
        if (! Str::isUuid($tenantId)) {
            throw new InvalidArgumentException('Channel provider health reconciliation input is invalid.');
        }

        [$states, $staleAfterSeconds, $futureToleranceSeconds] = $this->healthPolicy();

        $normalizedObservedAt = $observedAt->utc()->startOfSecond();
        $normalizedNow = CarbonImmutable::now()->utc()->startOfSecond();
        if (! $normalizedObservedAt->equalTo($normalizedNow)) {
            throw new InvalidArgumentException('Channel provider health reconciliation input is invalid.');
        }

        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Channel provider health reconciliation input is invalid.');
        }

        $counts = [
            'eligible_connections' => 0,
            'current_available' => 0,
            'current_unavailable' => 0,
            'already_stale' => 0,
            'missing_health' => 0,
            'invalid_state' => 0,
            'provider_mismatch' => 0,
            'future_dated' => 0,
            'expired' => 0,
            'transition_candidates' => 0,
            'selected_candidates' => 0,
            'would_transition' => 0,
            'transitioned' => 0,
            'skipped_race' => 0,
        ];

        $candidateSnapshots = [];
        $futureBoundary = $normalizedObservedAt->addSeconds($futureToleranceSeconds);
        $pastBoundary = $normalizedObservedAt->subSeconds($staleAfterSeconds);

        $cursor = DB::table('channel_connections')
            ->leftJoin('channel_provider_health', function ($join): void {
                $join->on('channel_provider_health.tenant_id', '=', 'channel_connections.tenant_id')
                    ->on('channel_provider_health.channel', '=', 'channel_connections.channel')
                    ->on('channel_provider_health.connection_key', '=', 'channel_connections.connection_key');
            })
            ->where('channel_connections.tenant_id', $tenantId)
            ->where('channel_connections.status', 'active')
            ->whereNull('channel_connections.disconnected_at')
            ->whereNull('channel_connections.deletion_requested_at')
            ->whereNull('channel_connections.deleted_at')
            ->orderBy('channel_connections.channel')
            ->orderBy('channel_connections.connection_key')
            ->orderBy('channel_provider_health.id')
            ->select([
                'channel_connections.tenant_id as connection_tenant_id',
                'channel_connections.channel as connection_channel',
                'channel_connections.connection_key as connection_key',
                'channel_connections.provider_key as connection_provider_key',
                'channel_provider_health.id as health_id',
                'channel_provider_health.provider_key as health_provider_key',
                'channel_provider_health.status as health_status',
                'channel_provider_health.checked_at as health_checked_at',
                'channel_provider_health.updated_at as health_updated_at',
            ])
            ->useWritePdo()
            ->cursor();

        foreach ($cursor as $row) {
            $counts['eligible_connections']++;

            $healthId = $row->health_id;
            $healthStatus = $row->health_status;
            $rawCheckedAt = $row->health_checked_at;

            if ($healthId === null || $healthStatus === null || $rawCheckedAt === null) {
                $counts['missing_health']++;

                continue;
            }

            if (! in_array($healthStatus, $states, true) || ! is_string($rawCheckedAt) || trim($rawCheckedAt) === '') {
                $counts['invalid_state']++;

                continue;
            }

            try {
                $checkedAt = CarbonImmutable::parse($rawCheckedAt, 'UTC')->utc();
            } catch (\Throwable) {
                $counts['invalid_state']++;

                continue;
            }

            if ($healthStatus === 'stale') {
                $counts['already_stale']++;

                continue;
            }

            $connectionProviderKey = is_string($row->connection_provider_key)
                ? $row->connection_provider_key
                : (string) $row->connection_provider_key;
            $healthProviderKey = is_string($row->health_provider_key)
                ? $row->health_provider_key
                : (string) $row->health_provider_key;

            if (trim($connectionProviderKey) === ''
                || trim($healthProviderKey) === ''
                || ! hash_equals($connectionProviderKey, $healthProviderKey)) {
                $counts['provider_mismatch']++;
                $this->retainCandidate($candidateSnapshots, $limit, $row);

                continue;
            }

            if ($checkedAt->greaterThan($futureBoundary)) {
                $counts['future_dated']++;
                $this->retainCandidate($candidateSnapshots, $limit, $row);

                continue;
            }

            if ($checkedAt->lessThan($pastBoundary)) {
                $counts['expired']++;
                $this->retainCandidate($candidateSnapshots, $limit, $row);

                continue;
            }

            if ($healthStatus === 'unavailable') {
                $counts['current_unavailable']++;

                continue;
            }

            if ($healthStatus === 'available') {
                $counts['current_available']++;

                continue;
            }

            $counts['invalid_state']++;
        }
        unset($cursor);

        $counts['selected_candidates'] = count($candidateSnapshots);
        $counts['transition_candidates'] = $counts['provider_mismatch']
            + $counts['future_dated']
            + $counts['expired'];
        $counts['would_transition'] = $counts['selected_candidates'];

        if (! $dryRun) {
            foreach ($candidateSnapshots as $candidate) {
                $update = DB::table('channel_provider_health')
                    ->where('id', $candidate['health_id'])
                    ->where('tenant_id', $tenantId)
                    ->where('channel', $candidate['channel'])
                    ->where('connection_key', $candidate['connection_key'])
                    ->where('status', $candidate['status'])
                    ->where('checked_at', $candidate['checked_at']);

                if ($candidate['provider_key'] === null) {
                    $update->whereNull('provider_key');
                } else {
                    $update->where('provider_key', $candidate['provider_key']);
                }

                if ($candidate['updated_at'] === null) {
                    $update->whereNull('updated_at');
                } else {
                    $update->where('updated_at', $candidate['updated_at']);
                }

                $affected = $update->update([
                    'status' => 'stale',
                    'updated_at' => $normalizedObservedAt,
                ]);

                if ($affected === 1) {
                    $counts['transitioned']++;
                } elseif ($affected === 0) {
                    $counts['skipped_race']++;
                } else {
                    throw new RuntimeException('Channel provider health reconciliation violated its update boundary.');
                }
            }
        }

        $hasEligible = $counts['eligible_connections'] > 0;
        $allCurrentAvailable = $hasEligible
            && $counts['current_available'] === $counts['eligible_connections'];
        $status = ! $hasEligible ? 'empty' : ($allCurrentAvailable ? 'ok' : 'attention');
        $decision = ! $hasEligible
            ? 'no_eligible_connections'
            : ($allCurrentAvailable ? 'all_current_available' : 'health_attention_required');

        return [
            'schema' => 'dovra.channel-provider-health-reconciliation.v1',
            'status' => $status,
            'decision' => $decision,
            'mode' => $dryRun ? 'dry_run' : 'apply',
            'scope' => 'tenant',
            'observed_at' => $normalizedObservedAt->toIso8601String(),
            'policy' => [
                'states' => $states,
                'stale_after_seconds' => $staleAfterSeconds,
                'future_tolerance_seconds' => $futureToleranceSeconds,
            ],
            'batch_complete' => $counts['transition_candidates'] <= $limit,
            'counts' => $counts,
        ];
    }

    /** @return array{0: list<string>, 1: int, 2: int} */
    private function healthPolicy(): array
    {
        $states = config('dovra_channels.contract.health.states');
        $staleAfterSeconds = config('dovra_channels.contract.health.stale_after_seconds');
        $futureToleranceSeconds = config('dovra_channels.contract.health.future_tolerance_seconds');

        if (! is_array($states)
            || ! array_is_list($states)
            || $states !== ['available', 'unavailable', 'stale']
            || ! is_int($staleAfterSeconds)
            || $staleAfterSeconds < 1
            || $staleAfterSeconds > 86400
            || ! is_int($futureToleranceSeconds)
            || $futureToleranceSeconds < 0
            || $futureToleranceSeconds > $staleAfterSeconds) {
            throw new InvalidArgumentException('Channel provider health policy is invalid.');
        }

        return [$states, $staleAfterSeconds, $futureToleranceSeconds];
    }

    /** @param list<array<string, mixed>> $candidateSnapshots */
    private function retainCandidate(array &$candidateSnapshots, int $limit, object $row): void
    {
        if (count($candidateSnapshots) >= $limit) {
            return;
        }

        $candidateSnapshots[] = [
            'health_id' => $row->health_id,
            'tenant_id' => $row->connection_tenant_id,
            'channel' => $row->connection_channel,
            'connection_key' => $row->connection_key,
            'provider_key' => $row->health_provider_key,
            'status' => $row->health_status,
            'checked_at' => $row->health_checked_at,
            'updated_at' => $row->health_updated_at,
        ];
    }
}
