<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AI\AiEvaluationFixture;
use Tests\Support\AI\AiRuntimeSelectionFixture;

uses(RefreshDatabase::class);

it('emits bounded json and exits zero only for a deterministic passing release', function (): void {
    $tenant = Tenant::factory()->create();
    config()->set('ai.public.billing_tenant_id', (string) $tenant->id);
    $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
    $fixture = AiEvaluationFixture::create((string) $tenant->id, $selection);

    $exit = Artisan::call('dovra:ai-evaluate', [
        'evaluation-version' => $fixture['evaluationVersionId'],
        '--agent-version' => $selection->agentVersionId,
        '--provider-version' => $selection->providerVersionId,
        '--model-version' => $selection->modelVersionId,
        '--artifact-sha' => 'git:0123456789abcdef',
        '--json' => true,
    ]);
    $output = trim(Artisan::output());
    $json = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
    expect($exit)->toBe(0)
        ->and($json['status'])->toBe('passed')
        ->and($json['blockers'])->toBe([])
        ->and(array_keys($json))->toBe(['run_id', 'status', 'metrics', 'blockers'])
        ->and($output)->not->toContain('What did I sell today?')
        ->not->toContain($selection->secretReference);
});

it('exits non-zero for blockers invalid graphs and missing artifacts without sensitive output', function (): void {
    $tenant = Tenant::factory()->create();
    config()->set('ai.public.billing_tenant_id', (string) $tenant->id);
    $selection = AiRuntimeSelectionFixture::create((string) $tenant->id);
    $failed = AiEvaluationFixture::create((string) $tenant->id, $selection, ['p95_latency_ms' => 100]);

    $base = [
        'evaluation-version' => $failed['evaluationVersionId'],
        '--agent-version' => $selection->agentVersionId,
        '--provider-version' => $selection->providerVersionId,
        '--model-version' => $selection->modelVersionId,
        '--json' => true,
    ];
    $failedExit = Artisan::call('dovra:ai-evaluate', [...$base, '--artifact-sha' => 'git:fedcba9876543210']);
    $failedOutput = trim(Artisan::output());
    $failedJson = json_decode($failedOutput, true, 32, JSON_THROW_ON_ERROR);
    expect($failedExit)->toBe(1)
        ->and($failedJson['status'])->toBe('failed')
        ->and($failedJson['blockers'])->toContain('threshold_p95_latency_ms');

    $invalidExit = Artisan::call('dovra:ai-evaluate', $base);
    $invalidOutput = trim(Artisan::output());
    $invalidJson = json_decode($invalidOutput, true, 32, JSON_THROW_ON_ERROR);
    expect($invalidExit)->toBe(1)
        ->and($invalidJson['status'])->toBe('invalid')
        ->and($invalidOutput)->not->toContain('What did I sell today?')
        ->not->toContain($selection->systemPrompt);
});
