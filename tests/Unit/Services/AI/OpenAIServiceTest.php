<?php

declare(strict_types=1);

use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Exceptions\InsufficientAiCreditsException;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const AI_ESTIMATED_COST = 0.0111;
const AI_ACTUAL_COST = 0.002375;

beforeEach(function () {
    config(['services.openai.api_key' => 'test-api-key']);
});

function createAiActors(float $balance = 1.0): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()
        ->forUser($user)
        ->pro()
        ->create([
            'ai_balance' => $balance,
            'ai_reserved_balance' => 0.0,
        ]);

    return [$user, $tenant];
}

function makeOpenAiService(): OpenAIService
{
    return new OpenAIService(new BudgetEnforcer);
}

function assertTenantBalances(Tenant $tenant, float $balance, float $reserved): void
{
    $freshTenant = $tenant->fresh();

    expect($freshTenant)->not->toBeNull();
    expect(abs((float) $freshTenant->ai_balance - $balance))->toBeLessThan(0.000001);
    expect(abs((float) $freshTenant->ai_reserved_balance - $reserved))->toBeLessThan(0.000001);
}

function mockOpenAISuccessResponse(): array
{
    return [
        'choices' => [
            [
                'message' => [
                    'content' => json_encode([
                        'description' => '<p>Una descripcion de negocio</p>',
                        'seo_title' => 'Titulo SEO optimizado',
                        'seo_description' => 'Meta descripcion para SEO',
                        'tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5'],
                        'category_suggestion' => 'Comercio',
                    ]),
                ],
            ],
        ],
        'usage' => [
            'prompt_tokens' => 150,
            'completion_tokens' => 200,
        ],
    ];
}

test('reserves credits before making API call', function () {
    [$user, $tenant] = createAiActors();

    Http::fake([
        'api.openai.com/*' => function () use ($tenant) {
            assertTenantBalances($tenant, 1.0, AI_ESTIMATED_COST);

            return Http::response(mockOpenAISuccessResponse(), 200);
        },
    ]);

    $result = makeOpenAiService()->generateStructuredProfile(
        'Test Business',
        'Description',
        (string) $user->id,
        (string) $tenant->id,
    );

    expect($result['seo_title'])->toBe('Titulo SEO optimizado');
});

test('settles with actual cost after successful API call', function () {
    [$user, $tenant] = createAiActors();

    Http::fake([
        'api.openai.com/*' => Http::response(mockOpenAISuccessResponse(), 200),
    ]);

    makeOpenAiService()->generateStructuredProfile(
        'Test',
        'Desc',
        (string) $user->id,
        (string) $tenant->id,
    );

    assertTenantBalances($tenant, 1.0 - AI_ACTUAL_COST, 0.0);

    $usageLog = DB::table('ai_usage_logs')->first();

    expect($usageLog)->not->toBeNull();
    expect($usageLog->user_id)->toBe((string) $user->id);
    expect($usageLog->tenant_id)->toBe((string) $tenant->id);
    expect($usageLog->provider)->toBe('openai');
    expect($usageLog->model)->toBe('gpt-4o');
    expect((int) $usageLog->input_tokens)->toBe(150);
    expect((int) $usageLog->output_tokens)->toBe(200);
    expect(abs((float) $usageLog->cost - AI_ACTUAL_COST))->toBeLessThan(0.000001);
});

test('refunds credits when API call fails', function () {
    [$user, $tenant] = createAiActors();

    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    expect(fn () => makeOpenAiService()->generateStructuredProfile(
        'Test',
        'Desc',
        (string) $user->id,
        (string) $tenant->id,
    ))->toThrow(AiProviderUnavailableException::class);

    assertTenantBalances($tenant, 1.0, 0.0);
    expect(DB::table('ai_usage_logs')->count())->toBe(0);
});

test('throws InsufficientAiCreditsException when balance too low', function () {
    [$user, $tenant] = createAiActors(0.0);

    expect(fn () => makeOpenAiService()->generateStructuredProfile(
        'Test',
        'Desc',
        (string) $user->id,
        (string) $tenant->id,
    ))->toThrow(InsufficientAiCreditsException::class);
});

test('does not call API when reserve fails', function () {
    [$user, $tenant] = createAiActors(0.0);

    Http::fake([
        'api.openai.com/*' => Http::response(mockOpenAISuccessResponse(), 200),
    ]);

    try {
        makeOpenAiService()->generateStructuredProfile(
            'Test',
            'Desc',
            (string) $user->id,
            (string) $tenant->id,
        );
    } catch (InsufficientAiCreditsException) {
    }

    Http::assertNothingSent();
    assertTenantBalances($tenant, 0.0, 0.0);
});

test('throws AiMalformedResponseException for invalid JSON schema', function () {
    [$user, $tenant] = createAiActors();

    $invalidResponse = [
        'choices' => [
            [
                'message' => [
                    'content' => json_encode([
                        'invalid_key' => 'value',
                    ]),
                ],
            ],
        ],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
    ];

    Http::fake([
        'api.openai.com/*' => Http::response($invalidResponse, 200),
    ]);

    expect(fn () => makeOpenAiService()->generateStructuredProfile(
        'Test',
        'Desc',
        (string) $user->id,
        (string) $tenant->id,
    ))->toThrow(AiMalformedResponseException::class);

    assertTenantBalances($tenant, 1.0, 0.0);
    expect(DB::table('ai_usage_logs')->count())->toBe(0);
});

test('sanitizes XSS from AI response', function () {
    [$user, $tenant] = createAiActors();

    $responseWithXss = [
        'choices' => [
            [
                'message' => [
                    'content' => json_encode([
                        'description' => '<p>Safe</p><script>alert("XSS")</script>',
                        'seo_title' => 'Title',
                        'seo_description' => 'Description',
                        'tags' => ['a', 'b', 'c'],
                        'category_suggestion' => 'Cat',
                    ]),
                ],
            ],
        ],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
    ];

    Http::fake([
        'api.openai.com/*' => Http::response($responseWithXss, 200),
    ]);

    $result = makeOpenAiService()->generateStructuredProfile(
        'Test',
        'Desc',
        (string) $user->id,
        (string) $tenant->id,
    );

    expect($result['description'])->toBe('<p>Safe</p>alert("XSS")');
    expect($result['description'])->not->toContain('<script>');
});
