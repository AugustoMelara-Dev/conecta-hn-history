<?php

declare(strict_types=1);

function program12QueueOwnedRoot(): string
{
    return dirname(__DIR__, 3);
}

it('proves queue isolation retry and compensation contracts for dovra operations', function (): void {
    $root = program12QueueOwnedRoot();
    $contract = json_decode(
        (string) file_get_contents($root.'/deployment/dovra/program12-queue-resilience-contract.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect(array_keys($contract['queue_classes']))->toBe([
        'critical',
        'conversations',
        'channels',
        'workflows',
        'indexing',
        'imports',
        'analytics',
        'notifications',
        'maintenance',
    ]);

    foreach ($contract['queue_classes'] as $queue => $definition) {
        expect($definition)->toMatchArray([
            'tenant_context' => 'server-derived',
            'correlation_id' => 'required',
            'idempotency' => 'required',
            'version' => 'required',
            'compensation' => 'required',
        ]);

        expect($definition['timeout_seconds'])->toBeInt()->toBeGreaterThan(0)
            ->and($definition['retry']['max_attempts'])->toBeInt()->toBeGreaterThan(0)
            ->and($definition['retry']['backoff_seconds'])->toBeArray()
            ->and(in_array($definition['failure_mode'], ['fail_closed', 'degrade_read_only'], true))->toBeTrue()
            ->and($definition['cleanup'])->toContain('tenant-safe')
            ->and($definition['cleanup'])->toContain('idempotent');
    }

    expect($contract['operational_state_machine']['deterministic'])->toBeTrue()
        ->and($contract['operational_state_machine']['fixture'])
        ->toBe('deployment/dovra/program12-operational-contract-fixture.json')
        ->and($contract['operational_state_machine']['states'])
        ->toContain('retry_scheduled', 'compensation_requested', 'dead_letter', 'failed');
});

it('keeps shared queue seams explicitly incomplete outside the owned artifact set', function (): void {
    $root = program12QueueOwnedRoot();
    $contract = json_decode(
        (string) file_get_contents($root.'/deployment/dovra/program12-queue-resilience-contract.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $runbook = (string) file_get_contents($root.'/devops/runbooks/dovra/program12-observability-resilience.md');

    foreach ($contract['shared_runtime_seams'] as $seam) {
        expect($seam['status'])->toBe('incomplete')
            ->and($seam['reason'])->toContain('outside the Task 7 exclusive write set');
    }

    expect($runbook)
        ->toContain('shared config seam')
        ->toContain('compensation contract')
        ->toContain('retry limits')
        ->toContain('critical')
        ->toContain('conversations')
        ->toContain('notifications');
});
