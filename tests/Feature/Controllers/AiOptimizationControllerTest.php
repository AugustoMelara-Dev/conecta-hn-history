<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AiOptimizationController;
use App\Http\Requests\Api\OptimizeProfileRequest;
use App\Jobs\GenerateBusinessProfileJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('optimizeProfile dispatches GenerateBusinessProfileJob and returns 202', function () {
    // Arrange: Mock TenantContext (interface — safe to mock)
    $tenantContext = Mockery::mock(\App\Domain\Shared\Contracts\TenantContext::class);
    $tenantContext->shouldReceive('getTenantUuid')->once()->andReturn('00000000-0000-0000-0000-000000000001');

    $controller = new AiOptimizationController($tenantContext);
    $user = User::factory()->create();

    // Build a real OptimizeProfileRequest with valid data
    $payload = [
        'business_name' => 'Café Tegus',
        'description' => 'El mejor café de Honduras con granos seleccionados',
        'target_audience' => 'Millennials',
        'tone' => 'professional',
        'keywords' => ['café', 'honduras'],
    ];

    $request = OptimizeProfileRequest::create('/api/v1/ai/optimize', 'POST', $payload);
    $request->setContainer(app());
    $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
    $request->setUserResolver(fn () => $user);
    $request->validateResolved();

    // Act
    $response = $controller->optimizeProfile($request);

    // Assert: 202 Accepted
    expect($response->getStatusCode())->toBe(202);

    $data = $response->getData(true);
    expect($data['status'])->toBe('processing');
    expect($data['message'])->toBe('Profile optimization has been queued.');

    // Assert: Job dispatched with correct parameters
    Queue::assertPushed(GenerateBusinessProfileJob::class, function ($job) use ($user) {
        return $job->businessName === 'Café Tegus'
            && $job->tenantId === '00000000-0000-0000-0000-000000000001'
            && $job->userId === (string) $user->getAuthIdentifier()
            && $job->targetAudience === 'Millennials'
            && $job->tone === 'professional'
            && $job->keywords === ['café', 'honduras'];
    });
});

test('controller does not depend on OpenAIService', function () {
    $reflection = new ReflectionClass(AiOptimizationController::class);
    $params = $reflection->getConstructor()->getParameters();

    expect($params)->toHaveCount(1);
    expect($params[0]->getName())->toBe('tenantContext');
});

test('controller imports GenerateBusinessProfileJob not OpenAIService', function () {
    $source = file_get_contents(
        (new ReflectionClass(AiOptimizationController::class))->getFileName()
    );

    expect($source)->not->toContain('use App\Services\AI\OpenAIService');
    expect($source)->toContain('use App\Jobs\GenerateBusinessProfileJob');
});
