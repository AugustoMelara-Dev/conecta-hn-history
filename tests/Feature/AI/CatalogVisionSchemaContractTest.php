<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Shared\Contracts\SchemaValidatorInterface;
use App\Domain\Shared\Exceptions\SchemaViolationException;
use App\Infrastructure\Common\SchemaRegistry\SchemaRegistryInterface;
use Tests\TestCase;

final class CatalogVisionSchemaContractTest extends TestCase
{
    public function test_catalog_vision_schema_requires_the_full_product_enrichment_shape(): void
    {
        $schema = app(SchemaRegistryInterface::class)->getSchema('catalog.product.vision.v1');
        $required = $schema['required'] ?? [];

        foreach ([
            'short_description',
            'tags',
            'colors',
            'material',
            'dimensions',
            'variations',
            'condition',
            'warnings',
        ] as $field) {
            self::assertContains($field, $required);
        }
    }

    public function test_catalog_vision_payload_accepts_explicit_unknown_commercial_values(): void
    {
        app(SchemaValidatorInterface::class)->validate('catalog.product.vision.v1', [
            'name' => 'Café de altura',
            'description' => 'Bolsa de café tostado artesanal.',
            'short_description' => 'Café tostado artesanal.',
            'category_suggestion' => 'Alimentos y bebidas',
            'tags' => ['café', 'artesanal'],
            'colors' => ['café'],
            'material' => null,
            'dimensions' => null,
            'variations' => [],
            'condition' => 'new',
            'warnings' => [],
            'price' => null,
            'stock_quantity' => null,
            'unit' => null,
            'field_confidence' => [
                'name' => 0.98,
                'description' => 0.84,
                'short_description' => 0.84,
                'category_suggestion' => 0.72,
                'tags' => 0.7,
                'colors' => 0.5,
                'material' => 0.0,
                'dimensions' => 0.0,
                'variations' => 0.0,
                'condition' => 0.9,
                'warnings' => 0.8,
                'price' => 0.0,
                'stock_quantity' => 0.0,
                'unit' => 0.0,
            ],
            'overall_confidence' => 0.72,
            'review_reasons' => ['price_missing', 'stock_missing'],
        ]);

        self::assertTrue(true);
    }

    public function test_catalog_vision_payload_rejects_out_of_range_confidence(): void
    {
        $this->expectException(SchemaViolationException::class);

        app(SchemaValidatorInterface::class)->validate('catalog.product.vision.v1', [
            'name' => 'Producto inseguro',
            'description' => '',
            'short_description' => null,
            'category_suggestion' => null,
            'tags' => [],
            'colors' => [],
            'material' => null,
            'dimensions' => null,
            'variations' => [],
            'condition' => null,
            'warnings' => [],
            'price' => null,
            'stock_quantity' => null,
            'unit' => null,
            'field_confidence' => [
                'name' => 1.4,
                'description' => 0.2,
                'short_description' => 0.0,
                'category_suggestion' => 0.0,
                'tags' => 0.0,
                'colors' => 0.0,
                'material' => 0.0,
                'dimensions' => 0.0,
                'variations' => 0.0,
                'condition' => 0.0,
                'warnings' => 0.0,
                'price' => 0.0,
                'stock_quantity' => 0.0,
                'unit' => 0.0,
            ],
            'overall_confidence' => 1.4,
            'review_reasons' => [],
        ]);
    }
}
