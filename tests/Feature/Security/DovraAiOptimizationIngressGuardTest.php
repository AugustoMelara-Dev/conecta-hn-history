<?php

declare(strict_types=1);

use App\Application\Ai\PrivateAiAccessGate;
use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\DovraDataIngressGuard;
use App\Domain\Privacy\Dovra\SensitivePayloadRedactor;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Api\V1\AiOptimizationController;
use App\Http\Requests\Api\OptimizeProfileRequest;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\DovraTenantBusinessContextResolver;
use App\Jobs\GenerateBusinessProfileJob;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

it('returns 403 before resolving or inspecting unauthorized profile input', function (): void {
    $tenantId = '00000000-0000-4000-8000-000000000042';
    $tenantContext = profileTenantContext($tenantId);
    $user = profileIngressUser();
    $gate = Mockery::mock(PrivateAiAccessGate::class);
    $gate->shouldReceive('allows')->once()->with($user, $tenantId)->andReturnFalse();
    $redactor = new ProfileIngressRedactorStub(throwOnScrub: true);

    $response = profileIngressController($tenantContext, $gate, $redactor)
        ->optimizeProfile(profileIngressRequest($user));

    expect($response->getStatusCode())->toBe(403)
        ->and($redactor->calls)->toBe(0);
    Queue::assertNothingPushed();
});

it('uses canonical tenant business context and rejects prohibited clinic scope', function (): void {
    $category = Category::factory()->create(['slug' => 'salud', 'is_active' => true]);
    $tenant = Tenant::factory()->create([
        'business_type' => 'service',
        'category_id' => $category->id,
    ]);
    $user = profileIngressUser();
    $gate = Mockery::mock(PrivateAiAccessGate::class);
    $gate->shouldReceive('allows')->once()->with($user, (string) $tenant->id)->andReturnTrue();
    $redactor = new ProfileIngressRedactorStub;
    $request = profileIngressRequest($user, [
        'description' => 'clinical_notes: routine followup without identifiers',
    ]);

    $response = profileIngressController(profileTenantContext((string) $tenant->id), $gate, $redactor)
        ->optimizeProfile($request);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe([
            'message' => 'The submitted AI input is not allowed.',
            'code' => 'ai_input_not_allowed',
        ])
        ->and($response->getContent())->not->toContain('clinical_notes')
        ->and($response->getContent())->not->toContain('clinic_scope_prohibited');
    Queue::assertNothingPushed();
});

it('returns a redacted 503 when tenant business context is unavailable', function (): void {
    $tenantId = '00000000-0000-4000-8000-000000000099';
    $user = profileIngressUser();
    $gate = Mockery::mock(PrivateAiAccessGate::class);
    $gate->shouldReceive('allows')->once()->with($user, $tenantId)->andReturnTrue();
    $redactor = new ProfileIngressRedactorStub(throwOnScrub: true);

    $response = profileIngressController(profileTenantContext($tenantId), $gate, $redactor)
        ->optimizeProfile(profileIngressRequest($user, ['description' => 'RAW_CONTEXT_SECRET']));

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true))->toBe([
            'message' => 'AI input validation is not available.',
            'code' => 'ai_ingress_unavailable',
        ])
        ->and($response->getContent())->not->toContain('RAW_CONTEXT_SECRET')
        ->and($redactor->calls)->toBe(0);
    Queue::assertNothingPushed();
});

it('returns a redacted 503 when inspection throws', function (): void {
    $tenant = Tenant::factory()->create(['business_type' => 'retail']);
    $user = profileIngressUser();
    $gate = Mockery::mock(PrivateAiAccessGate::class);
    $gate->shouldReceive('allows')->once()->andReturnTrue();
    $redactor = new ProfileIngressRedactorStub(throwOnScrub: true);

    $response = profileIngressController(profileTenantContext((string) $tenant->id), $gate, $redactor)
        ->optimizeProfile(profileIngressRequest($user, ['description' => 'RAW_GUARD_SECRET']));

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getContent())->not->toContain('RAW_GUARD_SECRET')
        ->and($redactor->calls)->toBe(1);
    Queue::assertNothingPushed();
});

it('rejects malformed redacted payloads and dispatches only a valid redacted shape', function (): void {
    $tenant = Tenant::factory()->create(['business_type' => 'retail']);
    $user = profileIngressUser();
    $gate = Mockery::mock(PrivateAiAccessGate::class);
    $gate->shouldReceive('allows')->twice()->andReturnTrue();
    $malformed = new ProfileIngressRedactorStub(['business_name' => 'incomplete']);
    $controller = profileIngressController(profileTenantContext((string) $tenant->id), $gate, $malformed);

    $blocked = $controller->optimizeProfile(profileIngressRequest($user));

    expect($blocked->getStatusCode())->toBe(503);
    Queue::assertNothingPushed();

    $redacted = [
        'business_name' => 'Redacted Business',
        'description' => 'Redacted description for provider work',
        'target_audience' => 'Redacted audience',
        'tone' => 'formal',
        'keywords' => ['redacted'],
    ];
    $allowed = profileIngressController(
        profileTenantContext((string) $tenant->id),
        $gate,
        new ProfileIngressRedactorStub($redacted),
    )->optimizeProfile(profileIngressRequest($user));

    expect($allowed->getStatusCode())->toBe(202);
    Queue::assertPushed(GenerateBusinessProfileJob::class, fn (GenerateBusinessProfileJob $job): bool => $job->businessName === $redacted['business_name']
        && $job->description === $redacted['description']
        && $job->targetAudience === $redacted['target_audience']
        && $job->tone === $redacted['tone']
        && $job->keywords === $redacted['keywords']);
});

function profileIngressController(
    TenantContext $tenantContext,
    PrivateAiAccessGate $gate,
    ProfileIngressRedactorStub $redactor,
): AiOptimizationController {
    return new AiOptimizationController(
        $tenantContext,
        $gate,
        new DovraDataIngressGuard(
            new DataClassificationPolicy($redactor),
            new DovraIntegrationSeamRegistry,
        ),
        new DovraTenantBusinessContextResolver,
    );
}

function profileTenantContext(string $tenantId): TenantContext
{
    $context = new TenantContext;
    $context->setTenant($tenantId);

    return $context;
}

function profileIngressUser(): User
{
    return (new User)->forceFill([
        'id' => '00000000-0000-4000-8000-000000000123',
        'name' => 'Owner',
        'email' => 'owner@example.test',
    ]);
}

/** @param array<string, mixed> $overrides */
function profileIngressRequest(User $user, array $overrides = []): OptimizeProfileRequest
{
    $request = OptimizeProfileRequest::create('/api/v1/ai/optimize-profile', 'POST', [
        'business_name' => 'Original Business',
        'description' => 'Original description for provider work',
        'target_audience' => 'Original audience',
        'tone' => 'professional',
        'keywords' => ['original'],
        ...$overrides,
    ]);
    $request->setUserResolver(fn (): User => $user);
    $request->setContainer(app());
    $request->setRedirector(app(Redirector::class));
    $request->validateResolved();

    return $request;
}

final class ProfileIngressRedactorStub implements SensitivePayloadRedactor
{
    public int $calls = 0;

    /** @param array<string, mixed>|null $replacement */
    public function __construct(
        private readonly ?array $replacement = null,
        private readonly bool $throwOnScrub = false,
    ) {}

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function scrub(array $payload): array
    {
        $this->calls++;
        if ($this->throwOnScrub) {
            throw new RuntimeException('RAW_THROWN_GUARD_SECRET');
        }

        return $this->replacement ?? $payload;
    }
}
