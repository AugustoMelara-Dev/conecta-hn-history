<?php

declare(strict_types=1);

use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function makeOpenAIService(): OpenAIService
{
    return new OpenAIService(new class extends BudgetEnforcer {
        public function reserve(int|string $tenantId, float $estimatedCost, string $operation): string
        {
            return 'test-reservation';
        }

        public function settle(int|string $tenantId, float $estimatedCost, float $actualCost, string $reservationId, array $usageMetadata = []): void
        {
        }

        public function refund(int|string $tenantId, float $estimatedCost, string $reservationId, string $failureReason): void
        {
        }
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

    $result = makeOpenAIService()->generateStructuredProfile('Biz', 'Desc', 1, 1);

    expect($result['seo_title'])->toBe('Titulo SEO')
        ->and($result['tags'])->toBeArray();
});

test('throws provider unavailable after upstream failures', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'down'], 503),
    ]);

    expect(fn () => makeOpenAIService()->generateStructuredProfile('Biz', 'Desc', 1, 1))
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

    expect(fn () => makeOpenAIService()->generateStructuredProfile('Biz', 'Desc', 1, 1))
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

    $result = makeOpenAIService()->generateStructuredProfile('Biz', 'Desc', 1, 1);

    expect($result['description'])->not->toContain('<script>')
        ->and($result['description'])->toContain('<p>Safe</p>');
});
