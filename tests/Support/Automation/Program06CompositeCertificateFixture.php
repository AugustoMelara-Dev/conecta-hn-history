<?php

declare(strict_types=1);

namespace Tests\Support\Automation;

use App\Application\Automation\WorkflowDefinitionService;
use App\Infrastructure\Automation\DatabaseWorkflowRepository;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Test-only topology and semantic-state helper for Program 06.
 */
final class Program06CompositeCertificateFixture
{
    /**
     * @return array{
     *   subject_tenant:string,
     *   primary_actor:string,
     *   same_tenant_actor:string,
     *   foreign_tenant:string,
     *   foreign_actor:string,
     *   workflow_key:string,
     *   workflow_version:string
     * }
     */
    public static function create(): array
    {
        self::assertGuard();

        /** @var array{0:Tenant,1:User,2:User,3:Tenant,4:User} $topology */
        $topology = Model::withoutEvents(function (): array {
            $primary = User::factory()->create([
                'name' => 'Program 06 Primary Actor',
                'email' => 'program06-primary@example.test',
            ]);
            $subject = Tenant::factory()->forUser($primary)->pro()->create([
                'name' => 'Program 06 Subject Tenant',
            ]);
            $primary->forceFill(['tenant_id' => $subject->getKey()])->save();

            $sameTenantActor = User::factory()->create([
                'name' => 'Program 06 Same Tenant Sentinel',
                'email' => 'program06-same-tenant@example.test',
                'tenant_id' => $subject->getKey(),
            ]);

            $foreignActor = User::factory()->create([
                'name' => 'Program 06 Foreign Actor',
                'email' => 'program06-foreign@example.test',
            ]);
            $foreign = Tenant::factory()->forUser($foreignActor)->pro()->create([
                'name' => 'Program 06 Foreign Tenant',
            ]);
            $foreignActor->forceFill(['tenant_id' => $foreign->getKey()])->save();

            return [$subject, $primary, $sameTenantActor, $foreign, $foreignActor];
        });

        $subjectTenant = (string) $topology[0]->getKey();
        $primaryActor = (string) $topology[1]->getKey();
        $foreignTenant = (string) $topology[3]->getKey();
        $foreignActor = (string) $topology[4]->getKey();

        $definitions = new WorkflowDefinitionService(new DatabaseWorkflowRepository(DB::connection()));
        self::publishWorkflow($definitions, $subjectTenant, $primaryActor, 'certificate.local.read', [
            ['id' => 'read', 'type' => 'tool', 'action' => 'certificate.local.read'],
        ]);
        self::publishWorkflow($definitions, $subjectTenant, $primaryActor, 'certificate.local.wait', [
            ['id' => 'pause', 'type' => 'wait', 'delay_seconds' => 300],
            ['id' => 'resume-read', 'type' => 'tool', 'action' => 'certificate.local.wait-read'],
        ]);
        self::publishWorkflow($definitions, $subjectTenant, $primaryActor, 'certificate.local.retry', [
            [
                'id' => 'reserve',
                'type' => 'tool',
                'action' => 'certificate.local.reserve',
                'compensation_action' => 'certificate.local.release',
            ],
            [
                'id' => 'charge',
                'type' => 'tool',
                'action' => 'certificate.local.charge',
                'retry_limit' => 1,
                'retry_delay_seconds' => 30,
            ],
        ]);
        self::publishWorkflow($definitions, $foreignTenant, $foreignActor, 'certificate.foreign.sentinel', [
            ['id' => 'foreign-read', 'type' => 'tool', 'action' => 'certificate.foreign.sentinel'],
        ]);

        self::policy($subjectTenant, 'certificate.local.read', 'read', ['agent'], ['workforce']);
        self::policy($subjectTenant, 'certificate.local.wait-read', 'read', ['owner'], ['api']);
        self::policy($subjectTenant, 'certificate.confirmed', 'mutation', ['primary'], ['api'], true);
        self::policy($foreignTenant, 'certificate.foreign.sentinel', 'read', ['foreign'], ['api']);

        $foreignAuditId = (string) Str::uuid();
        $foreignConversationId = (string) Str::uuid();
        DB::table('dovra_agent_action_audits')->insert([
            'id' => $foreignAuditId,
            'tenant_id' => $foreignTenant,
            'agent_version_id' => $foreignActor,
            'conversation_id' => $foreignConversationId,
            'action_key' => 'certificate.foreign.sentinel',
            'idempotency_key' => 'program06-foreign-audit-sentinel',
            'input_hash' => hash('sha256', 'program06-foreign-audit'),
            'risk' => 'read',
            'policy_code' => 'sentinel',
            'confirmation_id' => null,
            'workflow_run_id' => null,
            'outcome_id' => null,
            'status' => 'completed',
            'metadata' => json_encode(['sentinel' => true], JSON_THROW_ON_ERROR),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);
        DB::table('automation_integration_idempotency')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant,
            'scope' => 'program06:foreign-sentinel',
            'idempotency_key' => 'program06-foreign-sentinel',
            'fingerprint' => hash('sha256', 'program06-foreign-sentinel'),
            'outcome' => json_encode(['sentinel' => true], JSON_THROW_ON_ERROR),
            'consumed_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        return [
            'subject_tenant' => $subjectTenant,
            'primary_actor' => $primaryActor,
            'same_tenant_actor' => (string) $topology[2]->getKey(),
            'foreign_tenant' => $foreignTenant,
            'foreign_actor' => $foreignActor,
            'workflow_key' => 'certificate.local.read',
            'workflow_version' => 'v1',
        ];
    }

    /** @return array<string, int|string> */
    public static function tenantState(string $tenantId): array
    {
        $tables = [
            'automation_workflow_definitions',
            'automation_workflow_versions',
            'automation_workflow_runs',
            'automation_workflow_steps',
            'automation_workflow_step_attempts',
            'automation_workflow_approvals',
            'automation_tool_policies',
            'automation_confirmation_attempts',
            'automation_webhook_deliveries',
            'automation_integration_idempotency',
            'dovra_agent_action_audits',
            'dovra_agent_outcomes',
            'outbox_messages',
        ];
        $rawRows = [];
        $counts = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException('Program 06 state table is missing: '.$table);
            }
            $rawRows[$table] = self::rowsForTenant($table, $tenantId);
            $counts[$table] = count($rawRows[$table]);
        }
        $allRows = [];
        foreach ($rawRows as $tableRows) {
            $allRows = array_merge($allRows, $tableRows);
        }
        $labels = self::relationshipLabels($allRows);
        $rows = [];
        foreach ($rawRows as $table => $tableRows) {
            $rows[$table] = array_map(static fn (array $row): array => self::normalizeRow($row, $labels), $tableRows);
        }
        ksort($rows);
        ksort($counts);

        return [
            'row_count' => array_sum($counts),
            'table_count' => count($tables),
            'counts_sha256' => self::hashState($counts),
            'state_sha256' => self::hashState($rows),
        ];
    }

    /** @return array<string, int|string> */
    public static function runState(string $runId, string $tenantId): array
    {
        $run = DB::table('automation_workflow_runs')
            ->where('tenant_id', $tenantId)
            ->where('id', $runId)
            ->first();
        if ($run === null) {
            throw new \RuntimeException('Program 06 workflow run is missing.');
        }
        $steps = DB::table('automation_workflow_steps')
            ->where('tenant_id', $tenantId)
            ->where('run_id', $runId)
            ->orderBy('step_key')
            ->get()
            ->map(static fn (object $row): array => [
                'step_key' => (string) $row->step_key,
                'step_type' => (string) $row->step_type,
                'status' => (string) $row->status,
                'attempts' => (int) $row->attempts,
                'action' => $row->action === null ? null : (string) $row->action,
                'last_error_code' => $row->last_error_code === null ? null : (string) $row->last_error_code,
            ])->all();
        $attempts = DB::table('automation_workflow_step_attempts')
            ->where('tenant_id', $tenantId)
            ->where('run_id', $runId)
            ->orderBy('step_key')
            ->orderBy('attempt_kind')
            ->orderBy('attempt_number')
            ->get()
            ->map(static fn (object $row): array => [
                'step_key' => (string) $row->step_key,
                'attempt_kind' => (string) $row->attempt_kind,
                'attempt_number' => (int) $row->attempt_number,
                'provider_tool_key' => (string) $row->provider_tool_key,
                'status' => (string) $row->status,
                'error_code' => $row->error_code === null ? null : (string) $row->error_code,
            ])->all();

        $state = [
            'status' => (string) $run->status,
            'workflow_key' => (string) $run->workflow_key,
            'workflow_version' => (string) $run->workflow_version,
            'current_step_key' => $run->current_step_key === null ? null : (string) $run->current_step_key,
            'last_error_code' => $run->last_error_code === null ? null : (string) $run->last_error_code,
            'step_count' => count($steps),
            'attempt_count' => count($attempts),
            'steps' => $steps,
            'attempts' => $attempts,
        ];

        return [
            'status' => (string) $run->status,
            'step_count' => count($steps),
            'attempt_count' => count($attempts),
            'state_sha256' => self::hashState($state),
        ];
    }

    public static function hashState(array $state): string
    {
        $normalize = static function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as $key => $item) {
                $value[$key] = $normalize($item);
            }

            return $value;
        };
        $normalized = $normalize($state);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private static function assertGuard(): void
    {
        if (app()->environment() !== 'testing') {
            throw new \RuntimeException('Program 06 composite fixture requires testing environment.');
        }
        if (strtolower((string) getenv('DOVRA_PROGRAM06_COMPOSITE_ALLOW')) !== 'true') {
            throw new \RuntimeException('Program 06 composite fixture guard is disabled.');
        }
        if ((string) getenv('DB_CONNECTION') !== 'sqlite' || (string) config('database.default') !== 'sqlite') {
            throw new \RuntimeException('Program 06 composite fixture requires SQLite.');
        }
        $database = (string) getenv('DOVRA_PROGRAM06_COMPOSITE_DATABASE');
        $environmentDatabase = (string) getenv('DB_DATABASE');
        $configuredDatabase = (string) config('database.connections.sqlite.database');
        if (! self::isAbsolute($database)
            || ! is_file($database)
            || $database !== $environmentDatabase
            || $database !== $configuredDatabase
            || self::isAlias($database)) {
            throw new \RuntimeException('Program 06 composite fixture requires one exact disposable SQLite database path.');
        }

        $cache = (string) getenv('APP_CONFIG_CACHE');
        if (! self::isAbsolute($cache)
            || file_exists($cache)
            || ! self::pathsEqual(dirname($cache), dirname($database))
            || self::isAlias(dirname($cache))) {
            throw new \RuntimeException('Program 06 composite fixture requires an owned nonexistent APP_CONFIG_CACHE path.');
        }
        if (strtolower((string) getenv('CACHE_STORE')) !== 'array'
            || strtolower((string) getenv('SESSION_DRIVER')) !== 'array') {
            throw new \RuntimeException('Program 06 composite fixture requires array cache and session stores.');
        }
    }

    private static function publishWorkflow(WorkflowDefinitionService $definitions, string $tenantId, string $actorId, string $key, array $steps): void
    {
        $draft = $definitions->createDraft($tenantId, $key, ['steps' => $steps], $actorId);
        $definitions->publishDraft($tenantId, $key, (string) $draft['version_id'], 'v1', $actorId);
    }

    private static function policy(string $tenantId, string $tool, string $maxRisk, array $roles, array $channels, bool $confirmationRequired = false): void
    {
        DB::table('automation_tool_policies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'tool' => $tool,
            'tool_version' => $tool === 'certificate.local.read' ? 'agent-action-v1' : '2026-08-01',
            'allowed_roles' => json_encode($roles, JSON_THROW_ON_ERROR),
            'allowed_plans' => json_encode(['pro'], JSON_THROW_ON_ERROR),
            'allowed_channels' => json_encode($channels, JSON_THROW_ON_ERROR),
            'max_risk' => $maxRisk,
            'confirmation_required' => $confirmationRequired,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private static function rowsForTenant(string $table, string $tenantId): array
    {
        if ($table === 'outbox_messages') {
            return DB::table($table)->get()->map(static function (object $row) use ($tenantId): ?array {
                $payload = is_string($row->payload ?? null)
                    ? json_decode($row->payload, true)
                    : (array) ($row->payload ?? []);
                if ((string) ($payload['meta']['tenant_id'] ?? '') !== $tenantId) {
                    return null;
                }

                return (array) $row;
            })->filter()->values()->all();
        }
        $query = DB::table($table);
        if (Schema::hasColumn($table, 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->map(static fn (object $row): array => (array) $row)->all();
    }

    /** @param array<string, mixed> $row @param array<string, string> $labels @return array<string, mixed> */
    private static function normalizeRow(array $row, array $labels): array
    {
        foreach (array_keys($row) as $key) {
            if (str_ends_with($key, '_at') || in_array($key, ['created_at', 'updated_at'], true)) {
                $row[$key] = $row[$key] !== null;
            }
            if (is_string($row[$key]) && in_array($key, ['definition', 'payload', 'output', 'metadata', 'allowed_roles', 'allowed_plans', 'allowed_channels', 'outcome', 'attribution_metadata'], true)) {
                $decoded = json_decode($row[$key], true);
                if (is_array($decoded)) {
                    $row[$key] = $decoded;
                }
            }
            if ($key === 'tenant_id' && $row[$key] !== null) {
                $row[$key] = 'tenant';
            } elseif (is_string($row[$key]) && isset($labels[strtolower($row[$key])])) {
                $row[$key] = $labels[strtolower($row[$key])];
            } elseif (is_array($row[$key])) {
                $row[$key] = self::normalizeNested($row[$key], $labels);
            }
        }
        ksort($row);

        return $row;
    }

    /** @param list<array<string, mixed>> $rows @return array<string, string> */
    private static function relationshipLabels(array $rows): array
    {
        $values = [];
        $collect = static function (mixed $value) use (&$collect, &$values): void {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $collect($item);
                }

                return;
            }
            if (is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iu', $value) === 1) {
                $values[strtolower($value)] = true;
            }
        };
        $collect($rows);
        $ids = array_keys($values);
        sort($ids, SORT_STRING);
        $labels = [];
        foreach ($ids as $index => $id) {
            $labels[$id] = 'ref:'.($index + 1);
        }

        return $labels;
    }

    /** @param array<string, mixed> $value @param array<string, string> $labels @return array<string, mixed> */
    private static function normalizeNested(array $value, array $labels): array
    {
        foreach ($value as $key => $item) {
            if ($key === 'tenant_id' && $item !== null) {
                $value[$key] = 'tenant';
            } elseif (is_string($item) && isset($labels[strtolower($item)])) {
                $value[$key] = $labels[strtolower($item)];
            } elseif (is_array($item)) {
                $value[$key] = self::normalizeNested($item, $labels);
            } elseif (is_string($item) && (str_ends_with((string) $key, '_at') || in_array($key, ['created_at', 'updated_at', 'serialized_at'], true))) {
                $value[$key] = true;
            }
        }

        ksort($value);

        return $value;
    }

    private static function isAlias(string $path): bool
    {
        $cursor = $path;
        while (true) {
            if (is_link($cursor)) {
                return true;
            }
            $real = realpath($cursor);
            if ($real !== false && ! self::pathsEqual($real, $cursor)) {
                return true;
            }
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                return false;
            }
            $cursor = $parent;
        }
    }

    private static function pathsEqual(string $left, string $right): bool
    {
        $left = str_replace('/', DIRECTORY_SEPARATOR, $left);
        $right = str_replace('/', DIRECTORY_SEPARATOR, $right);

        return DIRECTORY_SEPARATOR === '\\' ? strcasecmp($left, $right) === 0 : $left === $right;
    }

    private static function isAbsolute(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1;
    }
}
