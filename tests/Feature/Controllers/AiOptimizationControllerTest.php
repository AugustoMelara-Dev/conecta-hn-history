<?php

declare(strict_types=1);

use App\Application\Ai\PrivateAiAccessGate;
use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Api\V1\AiOptimizationController;
use App\Http\Requests\Api\OptimizeProfileRequest;
use App\Jobs\GenerateBusinessProfileJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

test('optimizeProfile dispatches provider work only after private AI gate allows it', function (): void {
    $tenantContext = Mockery::mock(TenantContext::class);
    $tenantContext->shouldReceive('getTenantId')->once()->andReturn(42);

    $user = new User(['name' => 'Owner', 'email' => 'owner@example.test']);
    $user->forceFill(['id' => 123]);

    $privateAiAccess = Mockery::mock(PrivateAiAccessGate::class);
    $privateAiAccess->shouldReceive('allows')->once()->with($user, 42)->andReturnTrue();

    $controller = new AiOptimizationController($tenantContext, $privateAiAccess);

    $request = OptimizeProfileRequest::create('/api/v1/ai/optimize', 'POST', [
        'business_name' => 'Cafe Tegus',
        'description' => 'Cafe hondureno con granos seleccionados',
        'target_audience' => 'Millennials',
        'tone' => 'professional',
        'keywords' => ['cafe', 'honduras'],
    ]);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
    $request->validateResolved();

    $response = $controller->optimizeProfile($request);

    expect($response->getStatusCode())->toBe(202);

    $data = $response->getData(true);
    expect($data['status'])->toBe('processing');
    expect($data['message'])->toBe('Profile optimization has been queued.');

    Queue::assertPushed(GenerateBusinessProfileJob::class, function (GenerateBusinessProfileJob $job): bool {
        return $job->businessName === 'Cafe Tegus'
            && $job->tenantId === 42
            && $job->userId === 123
            && $job->targetAudience === 'Millennials'
            && $job->tone === 'professional'
            && $job->keywords === ['cafe', 'honduras'];
    });
});

test('optimizeProfile denies tenants outside the private AI gate', function (): void {
    $tenantContext = Mockery::mock(TenantContext::class);
    $tenantContext->shouldReceive('getTenantId')->once()->andReturn(42);

    $user = new User(['name' => 'Owner', 'email' => 'owner@example.test']);

    $privateAiAccess = Mockery::mock(PrivateAiAccessGate::class);
    $privateAiAccess->shouldReceive('allows')->once()->with($user, 42)->andReturnFalse();

    $controller = new AiOptimizationController($tenantContext, $privateAiAccess);

    $request = OptimizeProfileRequest::create('/api/v1/ai/optimize', 'POST', [
        'business_name' => 'Cafe Tegus',
        'description' => 'Cafe hondureno con granos seleccionados',
        'target_audience' => 'Negocios locales',
        'tone' => 'professional',
        'keywords' => ['cafe', 'honduras'],
    ]);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
    $request->validateResolved();

    $response = $controller->optimizeProfile($request);

    expect($response->getStatusCode())->toBe(403);
    Queue::assertNothingPushed();
});

test('private AI is disabled by default', function (): void {
    expect(config('ai.private.enabled'))->toBeFalse();
    expect(config('ai.private.tenant_allowlist'))->toBe([]);
});

test('controller depends on tenant context and private AI gate only', function (): void {
    $reflection = new ReflectionClass(AiOptimizationController::class);
    $params = $reflection->getConstructor()->getParameters();

    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('tenantContext');
    expect($params[1]->getName())->toBe('privateAiAccess');
});

test('controller imports queued job but not OpenAIService directly', function (): void {
    $source = file_get_contents((new ReflectionClass(AiOptimizationController::class))->getFileName());

    expect($source)->not->toContain('use App\Services\AI\OpenAIService');
    expect($source)->toContain('use App\Jobs\GenerateBusinessProfileJob');
});
