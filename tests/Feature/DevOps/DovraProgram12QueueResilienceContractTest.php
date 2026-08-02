<?php

declare(strict_types=1);

it('documents queue isolation retry and compensation contracts for dovra operations', function (): void {
    $contract = json_decode(
        (string) file_get_contents(base_path('deployment/dovra/program12-queue-resilience-contract.json')),
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

    expect($contract['shared_runtime_seams']['queue_config']['path'])->toBe('config/queue.php')
        ->and($contract['shared_runtime_seams']['queue_config']['status'])->toBe('incomplete')
        ->and($contract['shared_runtime_seams']['horizon_config']['path'])->toBe('config/horizon.php')
        ->and($contract['shared_runtime_seams']['horizon_config']['status'])->toBe('incomplete');
});

it('keeps the runbook honest about queue topology living in shared config outside this slice', function (): void {
    $queueConfig = (string) file_get_contents(base_path('config/queue.php'));
    $horizonConfig = (string) file_get_contents(base_path('config/horizon.php'));
    $runbook = (string) file_get_contents(base_path('devops/runbooks/dovra/program12-observability-resilience.md'));

    expect($queueConfig)
        ->toContain("'default' => env('QUEUE_CONNECTION', 'database')")
        ->toContain("'redis' => [")
        ->and($horizonConfig)
        ->toContain("'queue' => ['default']")
        ->toContain("'tries' => 1")
        ->and($runbook)
        ->toContain('shared config seam')
        ->toContain('critical')
        ->toContain('conversations')
        ->toContain('notifications')
        ->toContain('compensation contract')
        ->toContain('retry limits');
});
