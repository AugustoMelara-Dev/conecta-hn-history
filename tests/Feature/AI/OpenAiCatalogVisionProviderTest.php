<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Catalog\CatalogVisionImage;
use App\Application\Shared\Contracts\SchemaValidatorInterface;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Infrastructure\AI\OpenAiCatalogVisionProvider;
use App\Infrastructure\Common\SchemaRegistry\SchemaRegistryInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenAiCatalogVisionProviderTest extends TestCase
{
    public function test_provider_requests_every_enrichment_field_and_forbids_invented_commercial_values(): void
    {
        config([
            'services.openai.api_key' => 'sk-catalog-test',
            'services.openai.chat_completions_endpoint' => 'https://api.openai.test/v1/chat/completions',
        ]);
        $payload = $this->validPayload();
        Http::fake([
            'api.openai.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($payload, JSON_THROW_ON_ERROR)]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 80],
            ]),
        ]);

        $provider = new OpenAiCatalogVisionProvider(
            new class extends BudgetEnforcer
            {
                public function reserve(int|string $tenantId, float $estimatedCost, string $operation): string
                {
                    return 'catalog-test-reservation';
                }

                /** @return array{version:string,credits_per_provider_usd:float,minimum_charge:float} */
                public function pricingSnapshot(string $tenantId, string $reservationId): array
                {
                    return ['version' => 'credits-v1-test', 'credits_per_provider_usd' => 20.0, 'minimum_charge' => 0.01];
                }

                public function settle(int|string $tenantId, float $estimatedCost, float $actualCost, string $reservationId, array $usageMetadata = []): void {}

                public function refund(int|string $tenantId, float $estimatedCost, string $reservationId, string $failureReason): void {}
            },
            app(SchemaValidatorInterface::class),
            app(SchemaRegistryInterface::class),
        );

        $result = $provider->analyze(new CatalogVisionImage(
            tenantId: 'a25122c0-a33e-4a07-91c2-8b00f4d2859e',
            itemId: 'a25122c1-009e-4fbe-b911-4e0d5c04aa59',
            originalName: 'taza.png',
            mimeType: 'image/png',
            contents: 'valid-image-bytes',
        ));

        self::assertSame('Taza artesanal', $result->payload['name']);
        Http::assertSent(function (Request $request): bool {
            $messages = $request->data()['messages'] ?? [];
            $prompt = json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            foreach (['descripción corta', 'etiquetas', 'colores', 'material', 'dimensiones', 'variaciones', 'condición', 'advertencias', 'precio', 'inventario'] as $term) {
                self::assertStringContainsString($term, mb_strtolower($prompt));
            }

            self::assertStringContainsString('null', mb_strtolower($prompt));

            return true;
        });
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'name' => 'Taza artesanal',
            'description' => 'Taza de cerámica hecha a mano.',
            'short_description' => 'Cerámica artesanal.',
            'category_suggestion' => 'Hogar',
            'tags' => ['artesanal'],
            'colors' => ['azul'],
            'material' => 'cerámica',
            'dimensions' => null,
            'variations' => [],
            'condition' => 'new',
            'warnings' => [],
            'price' => null,
            'stock_quantity' => null,
            'unit' => null,
            'field_confidence' => [
                'name' => 0.9,
                'description' => 0.8,
                'short_description' => 0.8,
                'category_suggestion' => 0.7,
                'tags' => 0.7,
                'colors' => 0.8,
                'material' => 0.8,
                'dimensions' => 0.0,
                'variations' => 0.0,
                'condition' => 0.8,
                'warnings' => 0.5,
                'price' => 0.0,
                'stock_quantity' => 0.0,
                'unit' => 0.0,
            ],
            'overall_confidence' => 0.75,
            'review_reasons' => ['price_missing', 'stock_missing'],
        ];
    }
}
