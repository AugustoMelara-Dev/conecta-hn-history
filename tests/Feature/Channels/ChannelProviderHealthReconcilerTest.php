<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Application\Channels\ChannelProviderHealthReconciler;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class ChannelProviderHealthReconcilerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_closed_dry_run_classifies_boundaries_and_preserves_foreign_state(): void
    {
        $observedAt = CarbonImmutable::parse('2026-08-03T12:00:00Z')->startOfSecond();
        CarbonImmutable::setTestNow($observedAt);
        $fixture = $this->createFixture($observedAt);
        $foreignBefore = $this->tenantStateHash($fixture['foreign_tenant']);
        $subjectBefore = $this->tenantStateHash($fixture['subject_tenant']);

        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        $report = app(ChannelProviderHealthReconciler::class)->reconcile(
            tenantId: strtoupper($fixture['subject_tenant']),
            observedAt: CarbonImmutable::now()->utc()->startOfSecond(),
            limit: 100,
            dryRun: true,
        );
        $queries = DB::connection()->getQueryLog();
        DB::connection()->disableQueryLog();

        self::assertSame([
            'schema', 'status', 'decision', 'mode', 'scope', 'observed_at',
            'policy', 'batch_complete', 'counts',
        ], array_keys($report));
        self::assertSame('dovra.channel-provider-health-reconciliation.v1', $report['schema']);
        self::assertSame('attention', $report['status']);
        self::assertSame('health_attention_required', $report['decision']);
        self::assertSame('dry_run', $report['mode']);
        self::assertSame('tenant', $report['scope']);
        self::assertSame($observedAt->toIso8601String(), $report['observed_at']);
        self::assertSame([
            'states', 'stale_after_seconds', 'future_tolerance_seconds',
        ], array_keys($report['policy']));
        self::assertSame(['available', 'unavailable', 'stale'], $report['policy']['states']);
        self::assertSame(300, $report['policy']['stale_after_seconds']);
        self::assertSame(0, $report['policy']['future_tolerance_seconds']);
        self::assertSame([
            'eligible_connections', 'current_available', 'current_unavailable',
            'already_stale', 'missing_health', 'invalid_state', 'provider_mismatch',
            'future_dated', 'expired', 'transition_candidates', 'selected_candidates',
            'would_transition', 'transitioned', 'skipped_race',
        ], array_keys($report['counts']));
        self::assertSame([
            'eligible_connections' => 10,
            'current_available' => 3,
            'current_unavailable' => 1,
            'already_stale' => 1,
            'missing_health' => 1,
            'invalid_state' => 1,
            'provider_mismatch' => 1,
            'future_dated' => 1,
            'expired' => 1,
            'transition_candidates' => 3,
            'selected_candidates' => 3,
            'would_transition' => 3,
            'transitioned' => 0,
            'skipped_race' => 0,
        ], $report['counts']);
        self::assertTrue($report['batch_complete']);
        self::assertSame(10, array_sum(array_intersect_key(
            $report['counts'], array_flip([
                'current_available', 'current_unavailable', 'already_stale', 'missing_health',
                'invalid_state', 'provider_mismatch', 'future_dated', 'expired',
            ]),
        )));
        self::assertSame(3, $report['counts']['provider_mismatch'] + $report['counts']['future_dated'] + $report['counts']['expired']);
        self::assertSame($foreignBefore, $this->tenantStateHash($fixture['foreign_tenant']));
        self::assertSame($subjectBefore, $this->tenantStateHash($fixture['subject_tenant']));
        self::assertCount(0, array_filter($queries, static fn (array $query): bool => preg_match('/\b(update|insert|delete|upsert)\b/i', $query['query']) === 1));

        $encoded = json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        foreach ([$fixture['subject_tenant'], $fixture['foreign_tenant'], 'provider.alpha', 'provider.other', '06-provider-mismatch', 'health-reason'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $encoded);
        }
    }

    public function test_validation_rejects_invalid_inputs_and_policy_before_any_query(): void
    {
        $observedAt = CarbonImmutable::parse('2026-08-03T12:00:00Z')->startOfSecond();
        CarbonImmutable::setTestNow($observedAt);
        $fixture = $this->createFixture($observedAt);
        $reconciler = app(ChannelProviderHealthReconciler::class);

        $cases = [
            'tenant' => static fn () => $reconciler->reconcile('not-a-uuid', $observedAt, 1, true),
            'limit-low' => static fn () => $reconciler->reconcile($fixture['subject_tenant'], $observedAt, 0, true),
            'limit-high' => static fn () => $reconciler->reconcile($fixture['subject_tenant'], $observedAt, 1001, true),
            'historical-time' => static fn () => $reconciler->reconcile($fixture['subject_tenant'], $observedAt->subSecond(), 1, true),
            'future-time' => static fn () => $reconciler->reconcile($fixture['subject_tenant'], $observedAt->addSecond(), 1, true),
        ];
        foreach ($cases as $case) {
            $this->assertInvalidWithoutQueries($case, 'Channel provider health reconciliation input is invalid.');
        }

        $original = config('dovra_channels.contract.health');
        $policies = [
            ['states', ['available', 'stale', 'unavailable']],
            ['states', ['available', 'unavailable', 'stale', 'extra']],
            ['states', ['available', 'unavailable', 'available']],
            ['states', 'available'],
            ['stale_after_seconds', '300'],
            ['stale_after_seconds', 300.0],
            ['stale_after_seconds', false],
            ['stale_after_seconds', true],
            ['stale_after_seconds', 0],
            ['stale_after_seconds', 86401],
            ['future_tolerance_seconds', -1],
            ['future_tolerance_seconds', 301],
            ['future_tolerance_seconds', 0.0],
            ['future_tolerance_seconds', false],
        ];
        try {
            foreach ($policies as [$key, $value]) {
                config()->set('dovra_channels.contract.health.'.$key, $value);
                $this->assertInvalidWithoutQueries(
                    fn () => $reconciler->reconcile($fixture['subject_tenant'], $observedAt, 1, true),
                    'Channel provider health policy is invalid.',
                );
                config()->set('dovra_channels.contract.health', $original);
            }
        } finally {
            config()->set('dovra_channels.contract.health', $original);
        }

        foreach (['states', 'stale_after_seconds', 'future_tolerance_seconds'] as $missingKey) {
            $missing = $original;
            unset($missing[$missingKey]);
            config()->set('dovra_channels.contract.health', $missing);
            $this->assertInvalidWithoutQueries(
                fn () => $reconciler->reconcile($fixture['subject_tenant'], $observedAt, 1, true),
                'Channel provider health policy is invalid.',
            );
            config()->set('dovra_channels.contract.health', $original);
        }

        DB::table('channel_provider_health')->where('tenant_id', $fixture['subject_tenant'])->where('connection_key', '01-current-available')->update([
            'checked_at' => 'not-a-valid-timestamp',
        ]);
        $malformedReport = $reconciler->reconcile($fixture['subject_tenant'], $observedAt, 100, true);
        self::assertSame(2, $malformedReport['counts']['invalid_state']);
        self::assertSame(2, $malformedReport['counts']['current_available']);
        self::assertSame(3, $malformedReport['counts']['transition_candidates']);
    }

    public function test_apply_is_bounded_replay_safe_and_changes_only_selected_health_fields(): void
    {
        $observedAt = CarbonImmutable::parse('2026-08-03T12:00:00Z')->startOfSecond();
        CarbonImmutable::setTestNow($observedAt);
        $fixture = $this->createFixture($observedAt);
        $foreignBefore = $this->tenantStateHash($fixture['foreign_tenant']);
        $nonHealthBefore = $this->nonHealthTenantStateHash($fixture['subject_tenant']);
        $immutableBefore = $this->healthImmutableSnapshot($fixture['subject_tenant']);

        $first = app(ChannelProviderHealthReconciler::class)->reconcile(
            $fixture['subject_tenant'], CarbonImmutable::now()->utc()->startOfSecond(), 2, false,
        );
        self::assertFalse($first['batch_complete']);
        self::assertSame(3, $first['counts']['transition_candidates']);
        self::assertSame(2, $first['counts']['selected_candidates']);
        self::assertSame(2, $first['counts']['would_transition']);
        self::assertSame(2, $first['counts']['transitioned']);
        self::assertSame(0, $first['counts']['skipped_race']);
        self::assertSame('stale', DB::table('channel_provider_health')->where('tenant_id', $fixture['subject_tenant'])->where('connection_key', '06-provider-mismatch')->value('status'));
        self::assertSame('stale', DB::table('channel_provider_health')->where('tenant_id', $fixture['subject_tenant'])->where('connection_key', '07-future')->value('status'));
        self::assertSame('available', DB::table('channel_provider_health')->where('tenant_id', $fixture['subject_tenant'])->where('connection_key', '08-expired')->value('status'));
        self::assertSame($observedAt->toDateTimeString(), DB::table('channel_provider_health')->where('tenant_id', $fixture['subject_tenant'])->where('connection_key', '06-provider-mismatch')->value('updated_at'));
        self::assertSame($immutableBefore, $this->healthImmutableSnapshot($fixture['subject_tenant']));
        self::assertSame($nonHealthBefore, $this->nonHealthTenantStateHash($fixture['subject_tenant']));
        self::assertSame($foreignBefore, $this->tenantStateHash($fixture['foreign_tenant']));

        $second = app(ChannelProviderHealthReconciler::class)->reconcile(
            $fixture['subject_tenant'], CarbonImmutable::now()->utc()->startOfSecond(), 2, false,
        );
        self::assertSame(1, $second['counts']['transition_candidates']);
        self::assertSame(1, $second['counts']['selected_candidates']);
        self::assertSame(1, $second['counts']['transitioned']);

        $healthBeforeReplay = $this->healthContentHash($fixture['subject_tenant']);
        $replay = app(ChannelProviderHealthReconciler::class)->reconcile(
            $fixture['subject_tenant'], CarbonImmutable::now()->utc()->startOfSecond(), 2, false,
        );
        self::assertSame(0, $replay['counts']['transition_candidates']);
        self::assertSame(0, $replay['counts']['selected_candidates']);
        self::assertSame(0, $replay['counts']['transitioned']);
        self::assertSame(0, $replay['counts']['skipped_race']);
        self::assertSame($healthBeforeReplay, $this->healthContentHash($fixture['subject_tenant']));
        self::assertSame($foreignBefore, $this->tenantStateHash($fixture['foreign_tenant']));
        self::assertSame($nonHealthBefore, $this->nonHealthTenantStateHash($fixture['subject_tenant']));
    }

    public function test_race_refresh_wins_for_expired_matching_candidate_without_retry(): void
    {
        $observedAt = CarbonImmutable::parse('2026-08-03T12:00:00Z')->startOfSecond();
        CarbonImmutable::setTestNow($observedAt);
        $fixture = $this->createSingleExpiredFixture($observedAt);
        $armed = true;
        $fired = 0;
        $healthId = $fixture['health_id'];
        DB::connection()->beforeExecuting(function (string $query, array $bindings, $connection) use (&$armed, &$fired, $healthId): void {
            if (! $armed || ! str_contains(strtolower($query), 'update "channel_provider_health"')) {
                return;
            }
            $armed = false;
            $fired++;
            $connection->table('channel_provider_health')->where('id', $healthId)->update([
                'status' => 'available',
                'checked_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $report = app(ChannelProviderHealthReconciler::class)->reconcile(
            $fixture['tenant_id'], CarbonImmutable::now()->utc()->startOfSecond(), 10, false,
        );

        self::assertSame(1, $fired);
        self::assertSame(0, $report['counts']['transitioned']);
        self::assertSame(1, $report['counts']['skipped_race']);
        self::assertSame('available', DB::table('channel_provider_health')->where('id', $healthId)->value('status'));
        self::assertSame(now()->toDateTimeString(), DB::table('channel_provider_health')->where('id', $healthId)->value('checked_at'));
    }

    public function test_command_json_exit_codes_and_invalid_boundary_are_closed(): void
    {
        $observedAt = CarbonImmutable::parse('2026-08-03T12:00:00Z')->startOfSecond();
        CarbonImmutable::setTestNow($observedAt);
        $fixture = $this->createFixture($observedAt);

        [$attentionExit, $attentionOutput] = $this->runCommand([
            '--tenant' => $fixture['subject_tenant'], '--limit' => '100', '--dry-run' => true, '--json' => true,
        ]);
        self::assertSame(1, $attentionExit);
        $attentionPayload = json_decode(trim($attentionOutput), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('attention', $attentionPayload['status']);
        self::assertSame('dry_run', $attentionPayload['mode']);

        [$applyExit, $applyOutput] = $this->runCommand([
            '--tenant' => $fixture['subject_tenant'], '--limit' => '2', '--json' => true,
        ]);
        self::assertSame(1, $applyExit);
        $applyPayload = json_decode(trim($applyOutput), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            'schema', 'status', 'decision', 'mode', 'scope', 'observed_at',
            'policy', 'batch_complete', 'counts',
        ], array_keys($applyPayload));
        self::assertSame('attention', $applyPayload['status']);
        self::assertSame('apply', $applyPayload['mode']);
        self::assertFalse($applyPayload['batch_complete']);
        self::assertSame(3, $applyPayload['counts']['transition_candidates']);
        self::assertSame(2, $applyPayload['counts']['selected_candidates']);
        self::assertSame(2, $applyPayload['counts']['would_transition']);
        self::assertSame(2, $applyPayload['counts']['transitioned']);
        self::assertSame(0, $applyPayload['counts']['skipped_race']);
        self::assertSame('stale', DB::table('channel_provider_health')->where('tenant_id', $fixture['subject_tenant'])->where('connection_key', '06-provider-mismatch')->value('status'));
        self::assertSame('stale', DB::table('channel_provider_health')->where('tenant_id', $fixture['subject_tenant'])->where('connection_key', '07-future')->value('status'));
        self::assertSame('available', DB::table('channel_provider_health')->where('tenant_id', $fixture['subject_tenant'])->where('connection_key', '08-expired')->value('status'));

        foreach (['', '1.5', '+2', ' 2 ', '0', '1001'] as $limit) {
            [$invalidExit, $invalidOutput] = $this->runCommand(array_filter([
                '--tenant' => $fixture['subject_tenant'], '--limit' => $limit, '--json' => true,
            ], static fn (mixed $value): bool => $value !== null));
            self::assertSame(2, $invalidExit);
            self::assertSame(
                '{"schema":"dovra.channel-provider-health-reconciliation.v1","status":"invalid","code":"invalid_request"}',
                trim($invalidOutput),
            );
        }

        [$invalidTenantExit, $invalidTenantOutput] = $this->runCommand([
            '--tenant' => 'not-a-valid-tenant', '--json' => true,
        ]);
        self::assertSame(2, $invalidTenantExit);
        self::assertSame(
            '{"schema":"dovra.channel-provider-health-reconciliation.v1","status":"invalid","code":"invalid_request"}',
            trim($invalidTenantOutput),
        );

        $healthPolicy = config('dovra_channels.contract.health');
        $healthHashBeforeInvalidPolicy = $this->tenantStateHash($fixture['subject_tenant']);
        try {
            config()->set('dovra_channels.contract.health.states', ['available']);
            [$invalidPolicyExit, $invalidPolicyOutput] = $this->runCommand([
                '--tenant' => $fixture['subject_tenant'], '--json' => true,
            ]);
            self::assertSame(2, $invalidPolicyExit);
            self::assertSame(
                '{"schema":"dovra.channel-provider-health-reconciliation.v1","status":"invalid","code":"invalid_request"}',
                trim($invalidPolicyOutput),
            );
            self::assertSame($healthHashBeforeInvalidPolicy, $this->tenantStateHash($fixture['subject_tenant']));
        } finally {
            config()->set('dovra_channels.contract.health', $healthPolicy);
        }

        [$noTenantExit, $noTenantOutput] = $this->runCommand(['--json' => true]);
        self::assertSame(2, $noTenantExit);
        self::assertSame('invalid', json_decode(trim($noTenantOutput), true, 512, JSON_THROW_ON_ERROR)['status']);

        $healthy = $this->createMinimalTenant($observedAt, 'healthy-only');
        [$okExit, $okOutput] = $this->runCommand([
            '--tenant' => $healthy, '--json' => true,
        ]);
        self::assertSame(0, $okExit);
        self::assertSame('ok', json_decode(trim($okOutput), true, 512, JSON_THROW_ON_ERROR)['status']);

        $empty = (string) Tenant::factory()->create()->getKey();
        [$emptyExit, $emptyOutput] = $this->runCommand([
            '--tenant' => $empty, '--json' => true,
        ]);
        self::assertSame(1, $emptyExit);
        self::assertSame('empty', json_decode(trim($emptyOutput), true, 512, JSON_THROW_ON_ERROR)['status']);

        [$humanExit, $humanOutput] = $this->runCommand([
            '--tenant' => $healthy,
        ]);
        self::assertSame(0, $humanExit);
        self::assertMatchesRegularExpression('/^status=ok mode=apply eligible=1 candidates=0 selected=0 transitioned=0 skipped-race=0 batch-complete=true$/', trim($humanOutput));
    }

    public function test_source_ratchets_and_authority_hashes_remain_closed(): void
    {
        $service = file_get_contents(base_path('app/Application/Channels/ChannelProviderHealthReconciler.php'));
        $command = file_get_contents(base_path('app/Console/Commands/ReconcileChannelProviderHealthCommand.php'));
        self::assertIsString($service);
        self::assertIsString($command);

        preg_match_all("/\\b(?:table|leftJoin)\\('([^']+)'/", $service, $tables);
        self::assertSame(['channel_connections', 'channel_provider_health'], array_values(array_unique($tables[1])));
        foreach ([
            "config('dovra_channels.contract.health.states')",
            "config('dovra_channels.contract.health.stale_after_seconds')",
            "config('dovra_channels.contract.health.future_tolerance_seconds')",
            'useWritePdo()', '->cursor()', 'connection_tenant_id', 'health_updated_at',
            '->where(\'tenant_id\', $tenantId)', '->where(\'status\', $candidate[\'status\'])',
            '->where(\'checked_at\', $candidate[\'checked_at\'])', 'whereNull(\'updated_at\')',
            "'status' => 'stale'", "'updated_at' => \$normalizedObservedAt",
        ] as $needle) {
            self::assertStringContainsString($needle, $service);
        }
        foreach (['insert(', 'insertOrIgnore', 'updateOrInsert', 'delete(', 'truncate(', 'snapshotCapability', 'ChannelAdapterInterface', 'Http::', 'Storage::', 'dispatch(', 'Log::', 'secret resolver'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $service);
        }
        $lowerCommand = strtolower($command);
        foreach (['schedule', 'tenant enumeration', 'observed-at', 'freshness override', 'channeladapterinterface', 'snapshotcapability', 'raw row', 'exception output', 'provider call'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $lowerCommand);
        }
        self::assertStringNotContainsString(strtolower('ChannelAdapterInterface'), $lowerCommand);
        self::assertStringNotContainsString(strtolower('snapshotCapability'), $lowerCommand);

        $expected = [
            'config/dovra_channels.php' => '6085F9544D339EA4E8E755773B644669063BC7392A9C98203972981B2DCCF03C',
            'app/Application/Channels/ChannelConnectionService.php' => 'A4E94D1BFB71C01CB8118C9A01A0D6EEFC4C920A8355C27C7D29067DB1ED1CB3',
            'app/Http/Controllers/Tenant/WorkforceController.php' => '2D2A1C093CDB3A47B774BE43FDADE0BDF7C5A0BAA788687B808932F55E452C61',
            'app/Http/Controllers/Api/V1/TenantWorkforceController.php' => '3525823A7B64BCBB92041CDDB6D883EE71A5DB996BE91FDCA94C5CE593CC1844',
            'database/migrations/2026_08_01_000002_expand_program07_channels.php' => 'B3F8391F9F8B1D6CD9BB1502A4DB0FE1B0281186C1F18C22B9CE501CB326F63C',
            'bootstrap/app.php' => '53E25D7C58355F630B0AC4C623C8AADAE47C4399058BA8B95C06B8DA1FBBDA46',
            'routes/console.php' => 'DBE7FF88A60D2F6C56378A497065D2F9C7012D812D253C81394CFB2498BE253A',
            'tests/Feature/Channels/Program07CompositeCertificationTest.php' => '0D14AD726AC4AB1A5E7F65D307A08DDD8B3ABEC11539A07A4E16BF9F46DEF498',
            'scripts/qa/certify-dovra-program07-composite.mjs' => 'BD5457CF98E1C1A8951B979630A15F649548CC0B329EEEB7B72DE2DA97E627D4',
        ];
        foreach ($expected as $path => $hash) {
            self::assertSame($hash, strtoupper(hash_file('sha256', base_path($path))));
        }
    }

    /** @param callable(): mixed $callback */
    private function assertInvalidWithoutQueries(callable $callback, string $message): void
    {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();
        try {
            $callback();
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
        } finally {
            self::assertSame([], DB::connection()->getQueryLog());
            DB::connection()->disableQueryLog();
        }
    }

    /** @param array<string, mixed> $parameters @return array{0:int,1:string} */
    private function runCommand(array $parameters): array
    {
        $exit = Artisan::call('dovra:channels-reconcile-provider-health', $parameters);

        return [$exit, Artisan::output()];
    }

    /** @return array{subject_tenant:string,foreign_tenant:string} */
    private function createFixture(CarbonImmutable $observedAt): array
    {
        $subject = Tenant::factory()->forUser(User::factory()->create())->create(['name' => 'Reconciler subject']);
        $foreign = Tenant::factory()->forUser(User::factory()->create())->create(['name' => 'Reconciler foreign']);
        $subjectId = (string) $subject->getKey();
        $foreignId = (string) $foreign->getKey();
        $now = $observedAt->toDateTimeString();
        $old = $observedAt->subSeconds(601)->toDateTimeString();
        $future = $observedAt->addSecond()->toDateTimeString();
        $lower = $observedAt->subSeconds(300)->toDateTimeString();

        $rows = [
            ['01-current-available', 'provider.alpha', 'available', $now, 'provider.alpha', 'subject-current'],
            ['02-current-unavailable', 'provider.alpha', 'unavailable', $now, 'provider.alpha', 'subject-unavailable'],
            ['03-already-stale', 'provider.alpha', 'stale', $old, 'provider.alpha', 'subject-stale'],
            ['04-missing-health', 'provider.alpha', null, null, null, null],
            ['05-invalid-status', 'provider.alpha', 'invalid', $now, 'provider.alpha', 'subject-invalid'],
            ['06-provider-mismatch', 'provider.alpha', 'available', $now, 'provider.other', 'health-reason'],
            ['07-future', 'provider.alpha', 'available', $future, 'provider.alpha', 'subject-future'],
            ['08-expired', 'provider.alpha', 'available', $old, 'provider.alpha', 'subject-expired'],
            ['09-lower-bound', 'provider.alpha', 'available', $lower, 'provider.alpha', 'subject-lower'],
            ['10-upper-bound', 'provider.alpha', 'available', $now, 'provider.alpha', 'subject-upper'],
        ];
        foreach ($rows as [$key, $provider, $status, $checkedAt, $healthProvider, $reason]) {
            $this->connection($subjectId, $key, $provider, $key === '04-missing-health' ? null : null);
            if ($status !== null) {
                $this->health($subjectId, $key, $healthProvider, $status, $checkedAt, $reason, $key === '08-expired');
            }
        }
        $this->connection($subjectId, '11-disconnected', 'provider.alpha', null, now()->toDateTimeString());
        $this->connection($subjectId, '12-deletion-requested', 'provider.alpha', null, null, now()->toDateTimeString());
        $this->connection($subjectId, '13-deleted', 'provider.alpha', null, null, null, now()->toDateTimeString());

        $this->connection($foreignId, '01-foreign-current', 'provider.foreign');
        $this->health($foreignId, '01-foreign-current', 'provider.foreign', 'available', $now, 'foreign-current');
        $this->connection($foreignId, '02-foreign-expired', 'provider.foreign');
        $this->health($foreignId, '02-foreign-expired', 'provider.foreign', 'available', $old, 'foreign-expired');

        return ['subject_tenant' => $subjectId, 'foreign_tenant' => $foreignId];
    }

    /** @return array{tenant_id:string,health_id:string} */
    private function createSingleExpiredFixture(CarbonImmutable $observedAt): array
    {
        $tenant = Tenant::factory()->create(['name' => 'Reconciler race']);
        $tenantId = (string) $tenant->getKey();
        $key = 'race-expired';
        $this->connection($tenantId, $key, 'provider.race');
        $healthId = $this->health($tenantId, $key, 'provider.race', 'available', $observedAt->subSeconds(601)->toDateTimeString(), 'race');

        return ['tenant_id' => $tenantId, 'health_id' => $healthId];
    }

    private function createMinimalTenant(CarbonImmutable $observedAt, string $key): string
    {
        $tenant = Tenant::factory()->create(['name' => 'Reconciler '.$key]);
        $tenantId = (string) $tenant->getKey();
        $this->connection($tenantId, $key, 'provider.alpha');
        $this->health($tenantId, $key, 'provider.alpha', 'available', $observedAt->toDateTimeString(), 'healthy');

        return $tenantId;
    }

    private function connection(string $tenantId, string $key, string $provider, ?string $connectedAt = null, ?string $disconnectedAt = null, ?string $deletionRequestedAt = null, ?string $deletedAt = null): void
    {
        DB::table('channel_connections')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'channel' => 'email',
            'connection_key' => $key, 'provider_key' => $provider, 'status' => 'active',
            'connected_at' => $connectedAt ?? now()->toDateTimeString(),
            'disconnected_at' => $disconnectedAt, 'deletion_requested_at' => $deletionRequestedAt,
            'deleted_at' => $deletedAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function health(string $tenantId, string $key, string $provider, string $status, string $checkedAt, string $reason, bool $nullUpdatedAt = false): string
    {
        $id = (string) Str::uuid();
        DB::table('channel_provider_health')->insert([
            'id' => $id, 'tenant_id' => $tenantId, 'channel' => 'email', 'connection_key' => $key,
            'provider_key' => $provider, 'status' => $status, 'reason' => $reason,
            'checked_at' => $checkedAt, 'created_at' => now(), 'updated_at' => $nullUpdatedAt ? null : now(),
        ]);

        return $id;
    }

    private function tenantStateHash(string $tenantId): string
    {
        $tables = ['channel_connections', 'channel_capability_snapshots', 'channel_provider_health', 'channel_identities', 'channel_consents', 'channel_delivery_outbox', 'channel_delivery_attempts', 'channel_webhook_claims'];
        $state = [];
        foreach ($tables as $table) {
            $state[$table] = DB::table($table)->where('tenant_id', $tenantId)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
        }

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function nonHealthTenantStateHash(string $tenantId): string
    {
        $tables = ['channel_connections', 'channel_capability_snapshots', 'channel_identities', 'channel_consents', 'channel_delivery_outbox', 'channel_delivery_attempts', 'channel_webhook_claims'];
        $state = [];
        foreach ($tables as $table) {
            $state[$table] = DB::table($table)->where('tenant_id', $tenantId)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
        }

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function healthContentHash(string $tenantId): string
    {
        $rows = DB::table('channel_provider_health')->where('tenant_id', $tenantId)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, array<string, mixed>> */
    private function healthImmutableSnapshot(string $tenantId): array
    {
        return DB::table('channel_provider_health')->where('tenant_id', $tenantId)->orderBy('id')->get([
            'id', 'tenant_id', 'channel', 'connection_key', 'provider_key', 'reason', 'checked_at', 'created_at',
        ])->mapWithKeys(static fn ($row): array => [(string) $row->id => (array) $row])->all();
    }
}
