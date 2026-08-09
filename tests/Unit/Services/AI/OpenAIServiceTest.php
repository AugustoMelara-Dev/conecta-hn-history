<?php

declare(strict_types=1);

use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\Http;

function makeOpenAIService(): OpenAIService
{
    return new OpenAIService(new class extends BudgetEnforcer
    {
        public function reserve(string $tenantId, float $estimatedCost, string $operation): string
        {
            return 'test-reservation';
        }

        /** @return array{version:string,credits_per_provider_usd:float,minimum_charge:float} */
        public function pricingSnapshot(string $tenantId, string $reservationId): array
        {
            return ['version' => 'credits-v1-test', 'credits_per_provider_usd' => 20.0, 'minimum_charge' => 0.01];
        }

        public function settle(string $tenantId, float $estimatedCost, float $actualCost, string $reservationId, array $usageMetadata = []): void {}

        public function refund(string $tenantId, float $estimatedCost, string $reservationId, string $failureReason): void {}
    });
}

beforeEach(function () {
    config(['services.openai.api_key' => 'sk-test']);
});

test('returns structured profile on valid response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'description' => '<p>Descripcion valida</p>',
                        'seo_title' => 'Titulo SEO',
                        'seo_description' => 'Meta SEO',
                        'tags' => ['uno', 'dos', 'tres'],
                        'category_suggestion' => 'Comercio',
                    ]),
                ],
            ]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ], 200),
    ]);

    $result = makeOpenAIService()->generateStructuredProfile('Biz', 'Desc', 1, '550e8400-e29b-41d4-a716-446655440000');

    expect($result['seo_title'])->toBe('Titulo SEO')
        ->and($result['tags'])->toBeArray();
});

test('throws provider unavailable after upstream failures', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'down'], 503),
    ]);

    expect(fn () => makeOpenAIService()->generateStructuredProfile('Biz', 'Desc', 1, '550e8400-e29b-41d4-a716-446655440000'))
        ->toThrow(AiProviderUnavailableException::class);
});

test('throws malformed response when schema is invalid', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode(['invalid' => 'payload']),
                ],
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
        ], 200),
    ]);

    expect(fn () => makeOpenAIService()->generateStructuredProfile('Biz', 'Desc', 1, '550e8400-e29b-41d4-a716-446655440000'))
        ->toThrow(AiMalformedResponseException::class);
});

test('sanitizes script tags from description', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'description' => '<p>Safe</p><script>alert("XSS")</script>',
                        'seo_title' => 'Title',
                        'seo_description' => 'Meta',
                        'tags' => ['a', 'b', 'c'],
                        'category_suggestion' => 'Cat',
                    ]),
                ],
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
        ], 200),
    ]);

    $result = makeOpenAIService()->generateStructuredProfile('Biz', 'Desc', 1, '550e8400-e29b-41d4-a716-446655440000');

    expect($result['description'])->not->toContain('<script>')
        ->and($result['description'])->toContain('<p>Safe</p>');
});
