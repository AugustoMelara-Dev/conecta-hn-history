<?php

declare(strict_types=1);

const PROGRAM12_REDACTION_TOKEN = '[REDACTED]';

/** @return array<string, mixed> */
function program12LoadJson(string $path): array
{
    if (! is_file($path)) {
        throw new RuntimeException('Required contract fixture is missing.');
    }

    $decoded = json_decode(
        (string) file_get_contents($path),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    if (! is_array($decoded)) {
        throw new RuntimeException('Required contract fixture must decode to an object.');
    }

    return $decoded;
}

function program12Require(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function program12NormalizedKey(string $key): string
{
    return strtolower(str_replace(['-', '_'], '', $key));
}

/** @param array<int, string> $forbiddenKeys */
function program12Redact(mixed $value, array $forbiddenKeys, int &$redactedFieldCount): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    $forbidden = array_map('program12NormalizedKey', $forbiddenKeys);
    $redacted = [];

    foreach ($value as $key => $item) {
        if (is_string($key) && in_array(program12NormalizedKey($key), $forbidden, true)) {
            $redacted[$key] = PROGRAM12_REDACTION_TOKEN;
            $redactedFieldCount++;

            continue;
        }

        $redacted[$key] = program12Redact($item, $forbiddenKeys, $redactedFieldCount);
    }

    return $redacted;
}

function program12RequireSafeIdentifier(mixed $value, string $prefix, string $field): void
{
    program12Require(
        is_string($value)
            && preg_match('/^'.preg_quote($prefix, '/').'[a-z0-9-]+$/D', $value) === 1,
        $field.' must be a non-sensitive '.$prefix.' identifier.',
    );
}

/** @param array<string, string> $ledger */
function program12RegisterIdempotency(array &$ledger, string $digest): string
{
    if (array_key_exists($digest, $ledger)) {
        return 'deduplicated';
    }

    $ledger[$digest] = 'accepted';

    return 'accepted';
}

/** @param array<string, mixed> $observabilityContract
 * @param  array<string, mixed>  $fixture
 * @return array<string, mixed>
 */
function program12SimulateTrace(array $observabilityContract, array $fixture): array
{
    $requiredKeys = $observabilityContract['correlation']['required_keys'] ?? [];
    $forbiddenKeys = $observabilityContract['redaction']['forbidden_keys'] ?? [];
    $safeContext = $fixture['trace']['safe_context'] ?? [];
    $events = $fixture['trace']['events'] ?? [];

    program12Require(is_array($requiredKeys) && $requiredKeys !== [], 'Trace correlation keys are not executable.');
    program12Require(is_array($forbiddenKeys) && $forbiddenKeys !== [], 'Trace redaction keys are not executable.');
    program12Require(is_array($safeContext), 'Trace fixture safe context is missing.');
    program12Require(is_array($events) && count($events) >= 4, 'Trace fixture needs at least four events.');

    program12RequireSafeIdentifier($safeContext['tenant_id'] ?? null, 'tenant-', 'tenant_id');
    program12RequireSafeIdentifier($safeContext['actor_id'] ?? null, 'actor-', 'actor_id');

    $correlationIds = [];
    $components = [];
    $redactedFieldCount = 0;
    $leakedValues = [];
    $sensitiveValues = $fixture['sensitive_values'] ?? [];

    program12Require(is_array($sensitiveValues) && $sensitiveValues !== [], 'Trace fixture has no sensitive-value assertions.');

    foreach ($events as $event) {
        program12Require(is_array($event), 'Trace event fixture must be an object.');

        $context = array_merge($safeContext, is_array($event['overrides'] ?? null) ? $event['overrides'] : []);

        foreach ($requiredKeys as $requiredKey) {
            program12Require(
                array_key_exists($requiredKey, $context)
                    && is_scalar($context[$requiredKey])
                    && (string) $context[$requiredKey] !== '',
                'Trace event is missing required key '.$requiredKey.'.',
            );
        }

        program12Require($context['tenant_id'] === $safeContext['tenant_id'], 'Trace tenant context changed during propagation.');
        program12Require($context['actor_id'] === $safeContext['actor_id'], 'Trace actor context changed during propagation.');
        program12Require($context['correlation_id'] === $safeContext['correlation_id'], 'Trace correlation_id changed during propagation.');

        $correlationIds[(string) $context['correlation_id']] = true;
        $components[] = (string) ($event['component'] ?? 'unknown');

        $redactedEvent = program12Redact([
            'trace_context' => $context,
            'payload' => $event['sensitive'] ?? [],
        ], $forbiddenKeys, $redactedFieldCount);
        $serialized = json_encode($redactedEvent, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        foreach ($sensitiveValues as $sensitiveValue) {
            if (is_string($sensitiveValue) && str_contains($serialized, $sensitiveValue)) {
                $leakedValues[] = 'fixture-sensitive-value';
            }
        }
    }

    $uniqueComponents = array_values(array_unique($components));
    sort($uniqueComponents);

    program12Require($leakedValues === [], 'Trace redaction leaked a fixture-sensitive value.');
    program12Require($redactedFieldCount > 0, 'Trace fixture did not exercise redaction.');

    return [
        'status' => 'proved',
        'event_count' => count($events),
        'required_keys' => array_values($requiredKeys),
        'correlation_id_count' => count($correlationIds),
        'safe_context' => [
            'request_id' => $safeContext['request_id'],
            'conversation_id' => $safeContext['conversation_id'],
            'correlation_id' => $safeContext['correlation_id'],
            'tenant_id' => $safeContext['tenant_id'],
            'actor_id' => $safeContext['actor_id'],
        ],
        'components' => $uniqueComponents,
        'redaction' => [
            'status' => 'proved',
            'redacted_field_count' => $redactedFieldCount,
            'leaked_values' => [],
            'replacement' => PROGRAM12_REDACTION_TOKEN,
        ],
    ];
}

/** @param array<string, mixed> $queueContract
 * @param  array<string, mixed>  $fixture
 * @param  array<string, mixed>  $trace
 * @return array<string, mixed>
 */
function program12SimulateQueues(array $queueContract, array $fixture, array $trace): array
{
    $queueClasses = array_keys($queueContract['queue_classes'] ?? []);
    $machine = $queueContract['operational_state_machine'] ?? [];
    $scenarios = $fixture['queue_scenarios'] ?? [];

    program12Require($queueClasses !== [], 'Queue class manifest is not executable.');
    program12Require(($machine['deterministic'] ?? false) === true, 'Queue state machine is not deterministic.');
    program12Require(($machine['failure_plan'] ?? null) === 'always_fail', 'Queue fixture must use the deterministic failure plan.');
    program12Require(is_array($scenarios), 'Queue state machine fixture is missing.');
    program12Require(array_keys($scenarios) === $queueClasses, 'Queue fixture does not cover the exact manifest queue order.');

    $requiredStates = $machine['states'] ?? [];
    $requiredScope = $machine['idempotency_scope'] ?? [];
    $terminalFailureStates = $machine['terminal_failure_states'] ?? [];
    program12Require(is_array($requiredStates) && is_array($requiredScope), 'Queue state machine metadata is incomplete.');
    foreach (['tenant_id', 'job', 'version', 'idempotency_key'] as $scopeKey) {
        program12Require(in_array($scopeKey, $requiredScope, true), 'Queue idempotency scope is missing '.$scopeKey.'.');
    }
    program12Require(in_array('retry_scheduled', $requiredStates, true), 'Queue state machine has no retry state.');
    program12Require(in_array('compensation_requested', $requiredStates, true), 'Queue state machine has no compensation state.');
    program12Require(in_array('dead_letter', $terminalFailureStates, true), 'Queue state machine has no dead-letter state.');
    program12Require(in_array('failed', $terminalFailureStates, true), 'Queue state machine has no failed state.');

    $queueResults = [];
    $ledger = [];
    $correlationId = (string) ($trace['safe_context']['correlation_id'] ?? '');
    program12Require($correlationId !== '', 'Queue simulation cannot propagate the trace correlation ID.');
    $terminalStates = [];

    foreach ($queueClasses as $queue) {
        $definition = $queueContract['queue_classes'][$queue] ?? [];
        $scenario = $scenarios[$queue] ?? [];
        $maxAttempts = $definition['retry']['max_attempts'] ?? 0;
        $backoffSeconds = $definition['retry']['backoff_seconds'] ?? [];

        program12Require(is_array($scenario), 'Queue scenario '.$queue.' is not an object.');
        program12Require(($scenario['failure_plan'] ?? null) === 'always_fail', 'Queue scenario '.$queue.' is not executable.');
        program12Require(is_int($maxAttempts) && $maxAttempts > 1, 'Queue '.$queue.' has no retry attempt contract.');
        program12Require(is_array($backoffSeconds) && count($backoffSeconds) === $maxAttempts, 'Queue '.$queue.' has no deterministic backoff sequence.');
        program12Require(($definition['timeout_seconds'] ?? 0) > 0, 'Queue '.$queue.' has no timeout contract.');
        program12Require(($definition['tenant_context'] ?? null) === 'server-derived', 'Queue '.$queue.' does not derive tenant context server-side.');
        program12Require(($definition['idempotency'] ?? null) === 'required', 'Queue '.$queue.' has no idempotency contract.');
        program12Require(($definition['compensation'] ?? null) === 'required', 'Queue '.$queue.' has no compensation contract.');

        program12RequireSafeIdentifier($scenario['tenant_id'] ?? null, 'tenant-', 'Queue '.$queue.' tenant_id');
        program12RequireSafeIdentifier($scenario['isolated_tenant_id'] ?? null, 'tenant-', 'Queue '.$queue.' isolated_tenant_id');
        program12Require($scenario['tenant_id'] !== $scenario['isolated_tenant_id'], 'Queue '.$queue.' does not provide a second tenant.');
        program12RequireSafeIdentifier($scenario['actor_id'] ?? null, 'actor-', 'Queue '.$queue.' actor_id');

        $attempts = [];
        $stateSequence = ['queued'];

        foreach (range(1, $maxAttempts) as $attempt) {
            $attempts[] = $attempt;
            $stateSequence[] = 'running';

            if ($attempt < $maxAttempts) {
                $stateSequence[] = 'retry_scheduled';
            }
        }

        $stateSequence[] = 'compensation_requested';
        $stateSequence[] = 'compensation_applied';
        $terminalState = ($definition['failure_mode'] ?? null) === 'fail_closed'
            ? 'dead_letter'
            : 'failed';
        $stateSequence[] = $terminalState;
        $terminalStates[] = $terminalState;

        program12Require($attempts === ($scenario['expected_attempts'] ?? []), 'Queue '.$queue.' attempt sequence is not proven.');
        program12Require($backoffSeconds === ($scenario['expected_backoff_seconds'] ?? []), 'Queue '.$queue.' backoff sequence is not proven.');
        program12Require($stateSequence === ($scenario['expected_state_sequence'] ?? []), 'Queue '.$queue.' state machine is not proven.');
        program12Require($terminalState === ($scenario['expected_terminal_state'] ?? null), 'Queue '.$queue.' terminal failure state is not proven.');
        foreach ($stateSequence as $state) {
            program12Require(in_array($state, $requiredStates, true), 'Queue '.$queue.' emitted an undeclared state.');
        }

        $scope = function (string $tenantId) use ($scenario): string {
            return implode('|', [
                $tenantId,
                (string) $scenario['job'],
                (string) $scenario['version'],
                (string) $scenario['idempotency_key'],
            ]);
        };
        $primaryDigest = hash('sha256', $scope((string) $scenario['tenant_id']));
        $primaryDigestAgain = hash('sha256', $scope((string) $scenario['tenant_id']));
        $isolatedDigest = hash('sha256', $scope((string) $scenario['isolated_tenant_id']));
        $sameTenantReplay = [
            program12RegisterIdempotency($ledger, $primaryDigest),
            program12RegisterIdempotency($ledger, $primaryDigestAgain),
        ];
        $crossTenantReplay = [program12RegisterIdempotency($ledger, $isolatedDigest)];

        program12Require($primaryDigest === $primaryDigestAgain, 'Queue '.$queue.' idempotency digest is not stable.');
        program12Require($primaryDigest !== $isolatedDigest, 'Queue '.$queue.' idempotency scope crosses tenants.');
        program12Require($sameTenantReplay === ['accepted', 'deduplicated'], 'Queue '.$queue.' duplicate replay is not idempotent.');
        program12Require($crossTenantReplay === ['accepted'], 'Queue '.$queue.' cross-tenant replay was incorrectly deduplicated.');

        $queueResults[$queue] = [
            'status' => 'proved',
            'correlation_id' => $correlationId,
            'tenant_id' => $scenario['tenant_id'],
            'actor_id' => $scenario['actor_id'],
            'job' => $scenario['job'],
            'event' => $scenario['event'],
            'version' => $scenario['version'],
            'max_attempts' => $maxAttempts,
            'timeout_seconds' => $definition['timeout_seconds'],
            'attempts' => $attempts,
            'backoff_seconds' => array_values($backoffSeconds),
            'state_sequence' => $stateSequence,
            'compensation_state' => 'compensation_applied',
            'terminal_state' => $terminalState,
            'idempotency_replay' => $sameTenantReplay,
            'cross_tenant_replay' => $crossTenantReplay,
            'idempotency_digest_prefix' => substr($primaryDigest, 0, 12),
        ];
    }

    sort($terminalStates);
    program12Require(in_array('dead_letter', $terminalStates, true), 'Queue simulation did not exercise dead-letter failure.');
    program12Require(in_array('failed', $terminalStates, true), 'Queue simulation did not exercise failed degradation.');

    return [
        'status' => 'proved',
        'queue_count' => count($queueResults),
        'queues' => $queueResults,
        'tenant_isolation' => [
            'status' => 'proved',
            'same_key_cross_tenant' => 'isolated',
            'scope' => array_values($requiredScope),
        ],
    ];
}

$root = dirname(__DIR__, 2);
$fixtureRelativePath = 'deployment/dovra/program12-operational-contract-fixture.json';

try {
    $observabilityContract = program12LoadJson($root.'/deployment/dovra/program12-observability-contract.json');
    $queueContract = program12LoadJson($root.'/deployment/dovra/program12-queue-resilience-contract.json');
    $fixture = program12LoadJson($root.'/'.$fixtureRelativePath);

    program12Require(($observabilityContract['operational_harness']['deterministic'] ?? false) === true, 'Observability harness metadata is not deterministic.');
    program12Require(($observabilityContract['operational_harness']['machine_executable'] ?? false) === true, 'Observability harness metadata is not executable.');
    program12Require(($queueContract['operational_state_machine']['fixture'] ?? null) === $fixtureRelativePath, 'Queue state machine fixture reference is missing.');

    $trace = program12SimulateTrace($observabilityContract, $fixture);
    $queue = program12SimulateQueues($queueContract, $fixture, $trace);
    $result = [
        'name' => 'program12-operational-contract-harness',
        'status' => 'proved',
        'contract' => [
            'machine_executable' => true,
            'deterministic' => true,
            'fixture' => $fixtureRelativePath,
            'trace_simulation' => 'event propagation with redaction assertion',
            'queue_simulation' => 'retry/idempotency/compensation state machine',
        ],
        'trace' => $trace,
        'queue' => $queue,
    ];
    $exitCode = 0;
} catch (Throwable $exception) {
    $result = [
        'name' => 'program12-operational-contract-harness',
        'status' => 'failed',
        'contract' => [
            'machine_executable' => true,
            'deterministic' => true,
            'fixture' => $fixtureRelativePath,
        ],
        'error' => $exception->getMessage(),
    ];
    $exitCode = 1;
}

if (in_array('--json', $argv, true)) {
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} else {
    echo $result['status'].PHP_EOL;
}

exit($exitCode);
