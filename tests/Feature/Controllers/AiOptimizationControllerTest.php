<?php

declare(strict_types=1);

use App\Application\Ai\PrivateAiAccessGate;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Api\V1\AiOptimizationController;
use App\Http\Requests\Api\OptimizeProfileRequest;
use App\Jobs\GenerateBusinessProfileJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

test('optimizeProfile dispatches provider work only after private AI gate allows it', function (): void {
    $tenantId = (string) Tenant::factory()->create(['business_type' => 'retail'])->id;
    $tenantContext = new TenantContext;
    $tenantContext->setTenant($tenantId);

    $user = new User(['name' => 'Owner', 'email' => 'owner@example.test']);
    $userId = '00000000-0000-4000-8000-000000000123';
    $user->forceFill(['id' => $userId]);

    $privateAiAccess = Mockery::mock(PrivateAiAccessGate::class);
    $privateAiAccess->shouldReceive('allows')->once()->with($user, $tenantId)->andReturnTrue();

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
    $request->setRedirector(app(Redirector::class));
    $request->validateResolved();

    $response = $controller->optimizeProfile($request);

    expect($response->getStatusCode())->toBe(202);

    $data = $response->getData(true);
    expect($data['status'])->toBe('processing');
    expect($data['message'])->toBe('Profile optimization has been queued.');

    Queue::assertPushed(GenerateBusinessProfileJob::class, function (GenerateBusinessProfileJob $job) use ($tenantId, $userId): bool {
        return $job->businessName === 'Cafe Tegus'
            && $job->tenantId === $tenantId
            && $job->userId === $userId
            && $job->targetAudience === 'Millennials'
            && $job->tone === 'professional'
            && $job->keywords === ['cafe', 'honduras'];
    });
});

test('optimizeProfile denies tenants outside the private AI gate', function (): void {
    $tenantId = '00000000-0000-4000-8000-000000000042';
    $tenantContext = new TenantContext;
    $tenantContext->setTenant($tenantId);

    $user = new User(['name' => 'Owner', 'email' => 'owner@example.test']);

    $privateAiAccess = Mockery::mock(PrivateAiAccessGate::class);
    $privateAiAccess->shouldReceive('allows')->once()->with($user, $tenantId)->andReturnFalse();

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
    $request->setRedirector(app(Redirector::class));
    $request->validateResolved();

    $response = $controller->optimizeProfile($request);

    expect($response->getStatusCode())->toBe(403);
    Queue::assertNothingPushed();
});

test('private AI is disabled by default', function (): void {
    expect(config('ai.private.enabled'))->toBeFalse();
    expect(config('ai.private.tenant_allowlist'))->toBe([]);
});

test('controller depends on tenant authority and Dovra ingress seams', function (): void {
    $reflection = new ReflectionClass(AiOptimizationController::class);
    $params = $reflection->getConstructor()->getParameters();

    expect($params)->toHaveCount(4);
    expect($params[0]->getName())->toBe('tenantContext');
    expect($params[1]->getName())->toBe('privateAiAccess');
    expect($params[2]->getName())->toBe('dovraIngress');
    expect($params[3]->getName())->toBe('businessContext');
});

test('controller imports queued job but not OpenAIService directly', function (): void {
    $source = file_get_contents((new ReflectionClass(AiOptimizationController::class))->getFileName());

    expect($source)->not->toContain('use App\Services\AI\OpenAIService');
    expect($source)->toContain('use App\Jobs\GenerateBusinessProfileJob');
});
