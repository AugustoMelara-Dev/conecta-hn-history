<?php

declare(strict_types=1);

use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Exceptions\InsufficientAiCreditsException;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->budgetEnforcer = Mockery::mock(BudgetEnforcer::class);
});

/**
 * Helper: Crear respuesta mock de OpenAI exitosa
 */
function mockOpenAISuccessResponse(): array
{
    return [
        'choices' => [
            [
                'message' => [
                    'content' => json_encode([
                        'description' => '<p>Una descripción de negocio</p>',
                        'seo_title' => 'Título SEO optimizado',
                        'seo_description' => 'Meta descripción para SEO',
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

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: RESERVE-EXECUTE-SETTLE PATTERN
// ═══════════════════════════════════════════════════════════════════════════

test('reserves credits before making API call', function () {
    $this->budgetEnforcer
        ->shouldReceive('reserve')
        ->once()
        ->with(1, Mockery::type('float'), 'profile_generation')
        ->andReturn('reservation-123');

    $this->budgetEnforcer
        ->shouldReceive('settle')
        ->once();

    Http::fake([
        'api.openai.com/*' => Http::response(mockOpenAISuccessResponse(), 200),
    ]);

    $service = new OpenAIService($this->budgetEnforcer);
    $result = $service->generateStructuredProfile('Test Business', 'Description', 1, 1);

    expect($result['seo_title'])->toBe('Título SEO optimizado');
});

test('settles with actual cost after successful API call', function () {
    $this->budgetEnforcer
        ->shouldReceive('reserve')
        ->andReturn('reservation-123');

    $this->budgetEnforcer
        ->shouldReceive('settle')
        ->once()
        ->withArgs(function ($tenantId, $estimatedCost, $actualCost, $reservationId, $metadata) {
            // Verificar que actualCost se calcula correctamente
            // 150 input * 2.50/1M + 200 output * 10.00/1M
            $expectedCost = (150 * 2.50 + 200 * 10.00) / 1_000_000;
            return $tenantId === 1 &&
                   abs($actualCost - $expectedCost) < 0.000001 &&
                   $reservationId === 'reservation-123' &&
                   $metadata['model'] === 'gpt-4o';
        });

    Http::fake([
        'api.openai.com/*' => Http::response(mockOpenAISuccessResponse(), 200),
    ]);

    $service = new OpenAIService($this->budgetEnforcer);
    $service->generateStructuredProfile('Test', 'Desc', 1, 1);
});

test('refunds credits when API call fails', function () {
    $this->budgetEnforcer
        ->shouldReceive('reserve')
        ->andReturn('reservation-123');

    $this->budgetEnforcer
        ->shouldReceive('refund')
        ->once()
        ->withArgs(function ($tenantId, $estimatedCost, $reservationId, $failureReason) {
            return $tenantId === 1 &&
                   $reservationId === 'reservation-123' &&
                   str_contains($failureReason, 'OpenAI Unavailable');
        });

    // Simular fallo de API (503)
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'Service unavailable'], 503),
    ]);

    $service = new OpenAIService($this->budgetEnforcer);

    expect(fn() => $service->generateStructuredProfile('Test', 'Desc', 1, 1))
        ->toThrow(AiProviderUnavailableException::class);
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: INSUFFICIENT CREDITS
// ═══════════════════════════════════════════════════════════════════════════

test('throws InsufficientAiCreditsException when balance too low', function () {
    $this->budgetEnforcer
        ->shouldReceive('reserve')
        ->andThrow(InsufficientAiCreditsException::forOperation(1, 'profile_generation', 0.01, 0.00));

    $service = new OpenAIService($this->budgetEnforcer);

    expect(fn() => $service->generateStructuredProfile('Test', 'Desc', 1, 1))
        ->toThrow(InsufficientAiCreditsException::class);
});

test('does not call API when reserve fails', function () {
    $this->budgetEnforcer
        ->shouldReceive('reserve')
        ->andThrow(InsufficientAiCreditsException::forOperation(1, 'test', 0.01, 0.00));

    // HTTP::fake pero NO debería ser llamado
    Http::fake([
        'api.openai.com/*' => Http::response(mockOpenAISuccessResponse(), 200),
    ]);

    $service = new OpenAIService($this->budgetEnforcer);

    try {
        $service->generateStructuredProfile('Test', 'Desc', 1, 1);
    } catch (InsufficientAiCreditsException) {
        // Esperado
    }

    // Verificar que la API NO fue llamada
    Http::assertNothingSent();
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: SCHEMA VALIDATION STILL WORKS
// ═══════════════════════════════════════════════════════════════════════════

test('throws AiMalformedResponseException for invalid JSON schema', function () {
    $this->budgetEnforcer
        ->shouldReceive('reserve')
        ->andReturn('reservation-123');

    $this->budgetEnforcer
        ->shouldReceive('refund')
        ->once(); // Debe reembolsar cuando la validación falla

    $invalidResponse = [
        'choices' => [
            [
                'message' => [
                    'content' => json_encode([
                        // Falta 'description', 'seo_title', etc.
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

    $service = new OpenAIService($this->budgetEnforcer);

    expect(fn() => $service->generateStructuredProfile('Test', 'Desc', 1, 1))
        ->toThrow(AiMalformedResponseException::class);
});

// ═══════════════════════════════════════════════════════════════════════════
// TESTS: XSS SANITIZATION STILL WORKS
// ═══════════════════════════════════════════════════════════════════════════

test('sanitizes XSS from AI response', function () {
    $this->budgetEnforcer
        ->shouldReceive('reserve')
        ->andReturn('reservation-123');

    $this->budgetEnforcer
        ->shouldReceive('settle')
        ->once();

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

    $service = new OpenAIService($this->budgetEnforcer);
    $result = $service->generateStructuredProfile('Test', 'Desc', 1, 1);

    // Script tag debe ser eliminado
    expect($result['description'])->toBe('<p>Safe</p>alert("XSS")');
    expect($result['description'])->not->toContain('<script>');
});
