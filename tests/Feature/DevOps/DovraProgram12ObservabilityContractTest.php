<?php

declare(strict_types=1);

use App\Infrastructure\Observability\SentryEventScrubber;

it('documents tenant safe observability correlation and redaction contracts', function (): void {
    $contract = json_decode(
        (string) file_get_contents(base_path('deployment/dovra/program12-observability-contract.json')),
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
        ->and($contract['shared_runtime_seams']['logging']['path'])->toBe('config/logging.php')
        ->and($contract['shared_runtime_seams']['sentry']['path'])->toBe('config/sentry.php')
        ->and($contract['shared_runtime_seams']['opentelemetry']['path'])->toBe('config/observability.php')
        ->and($contract['shared_runtime_seams']['runtime_topology']['status'])->toBe('incomplete');

    $runbook = (string) file_get_contents(base_path('devops/runbooks/dovra/program12-observability-resilience.md'));

    expect($runbook)
        ->toContain('TraceContextInterface')
        ->toContain('non-sensitive tenant/actor identifiers only')
        ->toContain('commercial outcome')
        ->toContain('provider outage')
        ->toContain('abnormal AI cost')
        ->toContain('webhook replay')
        ->toContain('external')
        ->toContain('managed AWS');
});

it('anchors the contract to the existing fail closed sentry and otel configuration', function (): void {
    $sentry = require base_path('config/sentry.php');
    $observability = require base_path('config/observability.php');
    $scrubber = (string) file_get_contents(base_path('app/Infrastructure/Observability/SentryEventScrubber.php'));

    expect($sentry['send_default_pii'])->toBeFalse()
        ->and($sentry['before_send'])->toBe([
            SentryEventScrubber::class,
            'scrub',
        ])
        ->and($sentry['breadcrumbs']['sql_bindings'])->toBeFalse()
        ->and($sentry['tracing']['sql_bindings'])->toBeFalse()
        ->and($sentry['tracing']['redis_commands'])->toBeFalse()
        ->and($sentry['tracing']['gen_ai'])->toBeFalse()
        ->and($observability['opentelemetry']['enabled'])->toBeFalse()
        ->and($scrubber)->toContain("unset(\$request['cookies'], \$request['query_string']);")
        ->and($scrubber)->toContain("\$request['data'] = '[REDACTED]'")
        ->and($scrubber)->toContain("\$event->setUser(null);");
});
