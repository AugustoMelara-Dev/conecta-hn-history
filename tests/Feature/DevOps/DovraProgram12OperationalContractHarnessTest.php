<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function program12OperationalHarnessResult(): array
{
    $process = new Process([
        PHP_BINARY,
        base_path('scripts/dovra/program12-operational-contract-harness.php'),
        '--json',
    ], base_path());

    $process->setTimeout(20);
    $exitCode = $process->run();
    $stdout = trim($process->getOutput());

    return [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => trim($process->getErrorOutput()),
        'payload' => $stdout === '' ? [] : (json_decode($stdout, true) ?: []),
    ];
}

it('executes trace correlation and proves sensitive values are redacted', function (): void {
    $run = program12OperationalHarnessResult();

    expect($run['exit_code'])->toBe(0)
        ->and($run['payload']['status'] ?? null)->toBe('proved')
        ->and($run['payload']['contract']['machine_executable'] ?? null)->toBeTrue()
        ->and($run['payload']['trace']['status'] ?? null)->toBe('proved')
        ->and($run['payload']['trace']['event_count'] ?? 0)->toBeGreaterThanOrEqual(4)
        ->and($run['payload']['trace']['correlation_id_count'] ?? 0)->toBe(1)
        ->and($run['payload']['trace']['safe_context']['tenant_id'] ?? null)->toBe('tenant-01')
        ->and($run['payload']['trace']['safe_context']['actor_id'] ?? null)->toBe('actor-01')
        ->and($run['payload']['trace']['components'] ?? [])->toBe([
            'cache',
            'conversation',
            'database',
            'integration',
            'job',
            'outcome',
            'provider_tool',
            'request',
        ])
        ->and($run['payload']['trace']['redaction']['status'] ?? null)->toBe('proved')
        ->and($run['payload']['trace']['redaction']['leaked_values'] ?? ['missing'])->toBe([])
        ->and($run['payload']['trace']['redaction']['redacted_field_count'] ?? 0)->toBeGreaterThan(0);

    $fixture = json_decode(
        (string) file_get_contents(base_path('deployment/dovra/program12-operational-contract-fixture.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    foreach ($fixture['sensitive_values'] as $sensitiveValue) {
        expect($run['stdout'])->not->toContain($sensitiveValue);
    }
});

it('executes every required queue state machine with retries idempotency and tenant isolation', function (): void {
    $run = program12OperationalHarnessResult();
    $requiredQueues = [
        'critical',
        'conversations',
        'channels',
        'workflows',
        'indexing',
        'imports',
        'analytics',
        'notifications',
        'maintenance',
    ];

    expect($run['exit_code'])->toBe(0)
        ->and($run['payload']['queue']['status'] ?? null)->toBe('proved')
        ->and($run['payload']['queue']['queue_count'] ?? 0)->toBe(count($requiredQueues))
        ->and($run['payload']['queue']['tenant_isolation']['status'] ?? null)->toBe('proved');

    foreach ($requiredQueues as $queue) {
        $result = $run['payload']['queue']['queues'][$queue] ?? [];
        $maxAttempts = $result['max_attempts'] ?? 0;

        expect($result['status'] ?? null)->toBe('proved')
            ->and($result['correlation_id'] ?? null)
            ->toBe($run['payload']['trace']['safe_context']['correlation_id'] ?? null)
            ->and($result['attempts'] ?? [])->toBe(range(1, $maxAttempts))
            ->and($result['backoff_seconds'] ?? [])->toHaveCount($maxAttempts)
            ->and($result['state_sequence'] ?? [])->toContain('retry_scheduled')
            ->and($result['state_sequence'] ?? [])->toContain('compensation_requested')
            ->and($result['state_sequence'] ?? [])->toContain('compensation_applied')
            ->and($result['idempotency_replay'] ?? [])->toBe(['accepted', 'deduplicated'])
            ->and($result['cross_tenant_replay'] ?? [])->toBe(['accepted']);
    }

    $terminalStates = array_column($run['payload']['queue']['queues'], 'terminal_state');

    expect($terminalStates)->toContain('dead_letter')
        ->and($terminalStates)->toContain('failed');
});

it('requires machine-readable harness and fixture references instead of prose-only contracts', function (): void {
    $observability = json_decode(
        (string) file_get_contents(base_path('deployment/dovra/program12-observability-contract.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $queues = json_decode(
        (string) file_get_contents(base_path('deployment/dovra/program12-queue-resilience-contract.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($observability['operational_harness']['script'] ?? null)
        ->toBe('scripts/dovra/program12-operational-contract-harness.php')
        ->and($observability['operational_harness']['fixture'] ?? null)
        ->toBe('deployment/dovra/program12-operational-contract-fixture.json')
        ->and($observability['operational_harness']['deterministic'] ?? false)
        ->toBeTrue()
        ->and($queues['operational_state_machine']['fixture'] ?? null)
        ->toBe('deployment/dovra/program12-operational-contract-fixture.json')
        ->and($queues['operational_state_machine']['states'] ?? [])
        ->toContain('retry_scheduled', 'compensation_requested', 'dead_letter', 'failed');
});
