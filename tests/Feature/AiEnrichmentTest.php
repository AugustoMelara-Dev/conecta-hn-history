<?php

declare(strict_types=1);

use App\Infrastructure\Jobs\AI\EnhanceTenantProfileJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AiProfileGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// Set API key before each test and reset mocks after
beforeEach(function () {
    config(['services.openai.key' => 'test-api-key']);
});

afterEach(function () {
    Mockery::close();
    Log::swap(new \Illuminate\Log\LogManager(app()));
});

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER: Create test tenant with unique slug
// ═══════════════════════════════════════════════════════════════════════════════
function createTestTenant(array $overrides = []): Tenant
{
    $user = User::factory()->create(['role' => 'seller']);

    return Tenant::forceCreate(array_merge([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-'.uniqid(),
        'is_active' => true,
        'is_ai_enhanced' => false,
        'subscription_status' => 'active',
        'plan_type' => 'pro',
    ], $overrides));
}

// ═══════════════════════════════════════════════════════════════════════════════
// HELPER: Mock successful AI response
// ═══════════════════════════════════════════════════════════════════════════════
function fakeSuccessfulOpenAiResponse(array $overrides = []): void
{
    $defaultResponse = [
        'status' => 'success',
        'business_name_clean' => 'Pulpería Don Mario',
        'tagline' => 'Tu tienda de confianza',
        'short_description' => 'La mejor pulpería de Tegucigalpa',
        'full_description_html' => '<p>Bienvenidos a nuestra pulpería.</p>',
        'seo_keywords' => ['pulpería', 'tegucigalpa', 'tienda'],
        'risk_analysis' => 'Low risk - standard retail business',
    ];

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode(array_merge($defaultResponse, $overrides)),
                    ],
                ],
            ],
        ], 200),
    ]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// SERVICE TESTS: AiProfileGeneratorService
// ═══════════════════════════════════════════════════════════════════════════════

test('service throws exception when API key is not configured', function () {
    config(['services.openai.key' => null]);

    $service = new AiProfileGeneratorService;

    expect(fn () => $service->generate('Test', 'Description', 'Category'))
        ->toThrow(Exception::class, 'OpenAI API key is not configured');
});

test('service sanitizes XSS from HTML output', function () {
    config(['services.openai.key' => 'test-api-key']);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'status' => 'success',
                            'business_name_clean' => 'Test<script>alert(1)</script>',
                            'tagline' => 'Tagline<img onerror="alert(1)">',
                            'short_description' => 'Short<iframe>evil</iframe>',
                            'full_description_html' => '<p>Safe</p><script>evil</script><ul><li>Item</li></ul>',
                            'seo_keywords' => ['test'],
                            'risk_analysis' => 'None',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new AiProfileGeneratorService;
    $result = $service->generate('Test', 'Desc', 'Cat');

    // Verify XSS is stripped
    expect($result['business_name_clean'])->not->toContain('<script>');
    expect($result['tagline'])->not->toContain('onerror');
    expect($result['short_description'])->not->toContain('<iframe>');
    expect($result['full_description_html'])->not->toContain('<script>');
    expect($result['full_description_html'])->toContain('<p>Safe</p>');
    expect($result['full_description_html'])->toContain('<ul><li>Item</li></ul>');
});

test('service handles rate limit response correctly', function () {
    config(['services.openai.key' => 'test-api-key']);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'error' => ['message' => 'Rate limited'],
        ], 429, ['Retry-After' => '60']),
    ]);

    $service = new AiProfileGeneratorService;

    expect(fn () => $service->generate('Test', 'Desc', 'Cat'))
        ->toThrow(Exception::class, 'rate limited');
});

test('service validates required keys in response', function () {
    config(['services.openai.key' => 'test-api-key']);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'status' => 'success',
                            // Missing required keys
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new AiProfileGeneratorService;

    expect(fn () => $service->generate('Test', 'Desc', 'Cat'))
        ->toThrow(Exception::class, 'missing required keys');
});

test('service returns valid response on success', function () {
    config(['services.openai.key' => 'test-api-key']);
    fakeSuccessfulOpenAiResponse();

    $service = new AiProfileGeneratorService;
    $result = $service->generate('pulperia don mario', 'tienda local', 'Comercio');

    expect($result['status'])->toBe('success');
    expect($result['business_name_clean'])->toBe('Pulpería Don Mario');
    expect($result['seo_keywords'])->toBeArray();
    expect($result['seo_keywords'])->toContain('pulpería');
});

test('service handles flagged content response', function () {
    config(['services.openai.key' => 'test-api-key']);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'status' => 'flagged',
                            'business_name_clean' => 'Suspicious Business',
                            'tagline' => '',
                            'short_description' => '',
                            'full_description_html' => '',
                            'seo_keywords' => [],
                            'risk_analysis' => 'Detected references to prohibited substances',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new AiProfileGeneratorService;
    $result = $service->generate('Suspicious', 'drugs available', 'Unknown');

    expect($result['status'])->toBe('flagged');
    expect($result['risk_analysis'])->toContain('prohibited');
});

// ═══════════════════════════════════════════════════════════════════════════════
// JOB TESTS: EnhanceTenantProfileJob
// ═══════════════════════════════════════════════════════════════════════════════

test('job skips processing when tenant not found', function () {
    $job = new EnhanceTenantProfileJob(999999);
    $mockService = Mockery::mock(AiProfileGeneratorService::class);
    $mockService->shouldNotReceive('generate');

    $job->handle($mockService);
    // Test passes if no exception thrown - tenant not found is handled gracefully
    expect(true)->toBeTrue();
});

test('job skips already enriched tenants (idempotency)', function () {
    $tenant = createTestTenant([
        'is_ai_enhanced' => true, // Already processed
    ]);

    $job = new EnhanceTenantProfileJob($tenant->id);
    $mockService = Mockery::mock(AiProfileGeneratorService::class);
    $mockService->shouldNotReceive('generate');

    $job->handle($mockService);
});

test('job can be dispatched to queue', function () {
    Queue::fake();

    $tenant = createTestTenant();

    EnhanceTenantProfileJob::dispatch($tenant->id);

    Queue::assertPushed(EnhanceTenantProfileJob::class, function ($job) use ($tenant) {
        return $job->uniqueId() === 'enhance-tenant-'.$tenant->id;
    });
});

test('job has correct queue configuration', function () {
    $job = new EnhanceTenantProfileJob(1);

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([30, 60, 120]);
    expect($job->uniqueFor)->toBe(3600);
    expect($job->timeout)->toBe(120);
    expect($job->tenantId)->toBe(1);
});
