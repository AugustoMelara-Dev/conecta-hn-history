<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Domain\Product\Repositories\ProductRatingMetricsWriter;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductRatingMetricsWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_rating_enrichment_is_narrow_and_tenant_scoped(): void
    {
        $owner = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $product = Product::factory()->forTenant($owner)->create(['rating_avg' => 0, 'reviews_count' => 0]);
        $writer = app(ProductRatingMetricsWriter::class);

        self::assertFalse($writer->updateRatingMetrics((string) $other->getKey(), (int) $product->getKey(), 4.9, 8));
        self::assertSame(0.0, (float) $product->fresh()->rating_avg);
        self::assertSame(0, (int) $product->fresh()->reviews_count);

        self::assertTrue($writer->updateRatingMetrics((string) $owner->getKey(), (int) $product->getKey(), 4.9, 8));
        self::assertSame(4.9, (float) $product->fresh()->rating_avg);
        self::assertSame(8, (int) $product->fresh()->reviews_count);
    }
}
