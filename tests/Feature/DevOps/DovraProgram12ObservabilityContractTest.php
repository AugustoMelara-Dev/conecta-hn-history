<?php

declare(strict_types=1);

function program12ObservabilityOwnedRoot(): string
{
    return dirname(__DIR__, 3);
}

it('proves owned tenant-safe observability correlation and redaction contracts', function (): void {
    $root = program12ObservabilityOwnedRoot();
    $contract = json_decode(
        (string) file_get_contents($root.'/deployment/dovra/program12-observability-contract.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($contract['name'])->toBe('program12-observability-contract')
        ->and($contract['trace_context_interface'])->toBe('TraceContextInterface')
        ->and($contract['correlation']['required_keys'])->toBe([
            'request_id',
            'conversation_id',
            'correlation_id',
            'tenant_id',
            'actor_id',
            'job',
            'event',
            'database',
            'cache',
            'provider',
            'tool',
            'integration',
            'commercial_outcome',
            'idempotency_key',
            'version',
        ])
        ->and($contract['redaction']['forbidden_keys'])->toContain(
            'authorization',
            'cookie',
            'set-cookie',
            'prompt',
            'message',
            'content',
            'secret',
            'token',
            'api_key',
        )
        ->and($contract['initial_objectives'])->toMatchArray([
            'availability_monthly' => '99.9%',
            'non_ai_p95_ms' => 500,
            'healthy_provider_first_stream_p95_ms' => 2500,
            'critical_jobs_p95_ms' => 10000,
        ])
        ->and($contract['operational_harness']['machine_executable'])->toBeTrue()
        ->and($contract['operational_harness']['deterministic'])->toBeTrue();

    $runbook = (string) file_get_contents($root.'/devops/runbooks/dovra/program12-observability-resilience.md');

    expect($runbook)
        ->toContain('TraceContextInterface')
        ->toContain('non-sensitive tenant/actor identifiers only')
        ->toContain('commercial outcome')
        ->toContain('provider outage')
        ->toContain('abnormal AI cost')
        ->toContain('webhook replay')
        ->toContain('external')
        ->toContain('managed AWS')
        ->toContain('program12-operational-contract-harness.php');
});

it('keeps shared observability seams incomplete outside the owned artifact set', function (): void {
    $root = program12ObservabilityOwnedRoot();
    $contract = json_decode(
        (string) file_get_contents($root.'/deployment/dovra/program12-observability-contract.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    foreach (['logging', 'sentry', 'opentelemetry', 'runtime_topology'] as $seam) {
        expect($contract['shared_runtime_seams'][$seam]['status'])->toBe('incomplete')
            ->and($contract['shared_runtime_seams'][$seam]['evidence'] ?? $contract['shared_runtime_seams'][$seam]['reason'] ?? '')
            ->toContain('outside');
    }
});
