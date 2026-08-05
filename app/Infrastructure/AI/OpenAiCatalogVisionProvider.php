<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\Catalog\CatalogVisionImage;
use App\Application\Ai\Catalog\CatalogVisionProviderInterface;
use App\Application\Ai\Catalog\CatalogVisionProviderResult;
use App\Application\Shared\Contracts\SchemaValidatorInterface;
use App\Domain\Billing\Services\AiCreditPricingService;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Infrastructure\Common\SchemaRegistry\SchemaRegistryInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class OpenAiCatalogVisionProvider implements CatalogVisionProviderInterface
{
    private const ESTIMATED_COST_USD = 0.02;

    public function __construct(
        private BudgetEnforcer $budgetEnforcer,
        private SchemaValidatorInterface $schemaValidator,
        private SchemaRegistryInterface $schemaRegistry,
        private ?AiCreditPricingService $creditPricing = null,
    ) {}

    public function analyze(CatalogVisionImage $image): CatalogVisionProviderResult
    {
        $apiKey = (string) (config('services.openai.api_key') ?: config('services.openai.key'));
        if ($apiKey === '') {
            throw new AiProviderUnavailableException('Catalog vision provider is not configured.');
        }

        $estimatedCredits = $this->creditsFor(self::ESTIMATED_COST_USD);
        $reservationId = $this->budgetEnforcer->reserve(
            tenantId: $image->tenantId,
            estimatedCost: $estimatedCredits,
            operation: 'catalog_product_vision',
        );
        $pricingSnapshot = $this->budgetEnforcer->pricingSnapshot($image->tenantId, $reservationId);
        $startedAt = hrtime(true);

        try {
            $model = (string) config('services.openai.catalog_vision_model', 'gpt-4o-mini');
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(60)
                ->post((string) config('services.openai.chat_completions_endpoint'), [
                    'model' => $model,
                    'temperature' => 0,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'catalog_product_vision_v1',
                            'strict' => true,
                            'schema' => $this->schemaRegistry->getSchema('catalog.product.vision.v1'),
                        ],
                    ],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => implode(' ', [
                                'Analiza una foto de producto para crear un borrador revisable.',
                                'Describe nombre, descripción, descripción corta, categoría sugerida, etiquetas, colores, material, dimensiones, variaciones, condición, advertencias y unidad.',
                                'No inventes precio, inventario, dimensiones, variaciones, material ni advertencias: usa null o una lista vacía cuando la evidencia visual no sea inequívoca.',
                                'Asigna confianza por cada campo y explica en los motivos de revisión toda incertidumbre o dato comercial pendiente.',
                            ]),
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Devuelve únicamente el objeto JSON solicitado por el esquema, sin texto adicional.',
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => 'data:'.$image->mimeType.';base64,'.base64_encode($image->contents),
                                        'detail' => 'high',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                throw new AiProviderUnavailableException('Catalog vision provider returned HTTP '.$response->status().'.');
            }

            /** @var array<string, mixed> $raw */
            $raw = $response->json();
            $content = $raw['choices'][0]['message']['content'] ?? null;
            if (! is_string($content)) {
                throw new AiMalformedResponseException('Catalog vision response has no JSON content.');
            }

            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new AiMalformedResponseException('Catalog vision response is not an object.');
            }

            $this->schemaValidator->validate('catalog.product.vision.v1', $payload);
            $inputTokens = max(0, (int) ($raw['usage']['prompt_tokens'] ?? 0));
            $outputTokens = max(0, (int) ($raw['usage']['completion_tokens'] ?? 0));
            $cost = $this->calculateCost($inputTokens, $outputTokens);
            $chargedCredits = ($this->creditPricing ?? new AiCreditPricingService)->fromProviderUsdAtSnapshot(
                $cost,
                $pricingSnapshot['credits_per_provider_usd'],
                $pricingSnapshot['minimum_charge'],
            );
            $durationMs = max(0, (int) ((hrtime(true) - $startedAt) / 1_000_000));

            $this->budgetEnforcer->settle(
                tenantId: $image->tenantId,
                estimatedCost: $estimatedCredits,
                actualCost: $chargedCredits,
                reservationId: $reservationId,
                usageMetadata: [
                    'operation' => 'catalog_product_vision',
                    'model' => $model,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'duration_ms' => $durationMs,
                    'credit_pricing_version' => $pricingSnapshot['version'],
                    'credits_per_provider_usd' => $pricingSnapshot['credits_per_provider_usd'],
                    'credit_minimum_charge' => $pricingSnapshot['minimum_charge'],
                ],
            );

            return new CatalogVisionProviderResult(
                payload: $payload,
                provider: 'openai',
                model: $model,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                costUsd: $cost,
                durationMs: $durationMs,
                rawResponse: $raw,
            );
        } catch (Throwable $exception) {
            $this->budgetEnforcer->refund(
                tenantId: $image->tenantId,
                estimatedCost: $estimatedCredits,
                reservationId: $reservationId,
                failureReason: $exception::class,
            );

            if ($exception instanceof AiProviderUnavailableException || $exception instanceof AiMalformedResponseException) {
                throw $exception;
            }

            throw new AiProviderUnavailableException('Catalog vision provider failed.', 0, $exception);
        }
    }

    private function creditsFor(float $providerCostUsd): float
    {
        return ($this->creditPricing ?? new AiCreditPricingService)->fromProviderUsd($providerCostUsd);
    }

    private function calculateCost(int $inputTokens, int $outputTokens): float
    {
        $inputRate = (float) config('services.openai.catalog_input_usd_per_million', 0.15);
        $outputRate = (float) config('services.openai.catalog_output_usd_per_million', 0.60);

        return (($inputTokens * $inputRate) + ($outputTokens * $outputRate)) / 1_000_000;
    }
}
