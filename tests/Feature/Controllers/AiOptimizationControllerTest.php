<?php

declare(strict_types=1);

use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Api\V1\AiOptimizationController;
use App\Http\Requests\Api\OptimizeProfileRequest;
use App\Jobs\GenerateBusinessProfileJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('optimizeProfile dispatches GenerateBusinessProfileJob and returns 202', function () {
    $tenant = Tenant::factory()->pro()->create([
        'id' => '00000000-0000-0000-0000-000000000001',
        'business_type' => 'service',
        'ai_balance' => 1.0,
    ]);
    $tenantContext = new StubAiTenantContext((string) $tenant->id);
    $controller = new AiOptimizationController($tenantContext);
    $user = User::factory()->tenantOwner()->create([
        'tenant_id' => $tenant->id,
    ]);
    $tenant->forceFill(['user_id' => $user->id])->save();

    /** @var array<string, mixed> $payload */
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

    $response = $controller->optimizeProfile($request);

    expect($response->getStatusCode())->toBe(202);

    /** @var array<string, mixed> $data */
    $data = $response->getData(true);
    expect($data['status'])->toBe('processing');
    expect($data['message'])->toBe('Profile optimization has been queued.');

    Queue::assertPushed(GenerateBusinessProfileJob::class, function ($job) use ($user) {
        return $job->businessName === 'Café Tegus'
            && $job->tenantId === '00000000-0000-0000-0000-000000000001'
            && $job->userId === (string) $user->getAuthIdentifier()
            && $job->targetAudience === 'Millennials'
            && $job->tone === 'professional'
            && $job->keywords === ['café', 'honduras'];
    });
});

test('optimize profile request denies users without a tenant context', function () {
    $user = User::factory()->create([
        'tenant_id' => null,
    ]);

    $request = OptimizeProfileRequest::create('/api/v1/ai/optimize', 'POST', [
        'business_name' => 'Cafe Tegus',
        'description' => 'El mejor cafe de Honduras con granos seleccionados',
        'target_audience' => 'Millennials',
        'tone' => 'professional',
        'keywords' => ['cafe', 'honduras'],
    ]);
    $request->setContainer(app());
    $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeFalse();
});

test('controller does not depend on OpenAIService', function () {
    $reflection = new ReflectionClass(AiOptimizationController::class);
    $params = $reflection->getConstructor()?->getParameters() ?? [];

    expect($params)->toHaveCount(1);
    expect($params[0]->getName())->toBe('tenantContext');
});

test('controller imports GenerateBusinessProfileJob not OpenAIService', function () {
    $fileName = (new ReflectionClass(AiOptimizationController::class))->getFileName();
    expect($fileName)->not->toBeFalse();

    $source = file_get_contents((string) $fileName);

    expect($source)->toContain('use App\Jobs\GenerateBusinessProfileJob');
    expect($source)->not->toContain('use App\Services\AI\OpenAIService');
});

final class StubAiTenantContext implements TenantContext
{
    public function __construct(private readonly string $tenantUuid) {}

    public function getTenantUuid(): string
    {
        return $this->tenantUuid;
    }

    public function getTenantId(): int
    {
        return 0;
    }

    public function getTenantData(?array $only = null): array
    {
        return ['id' => $this->tenantUuid];
    }
}
