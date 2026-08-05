<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Domain\Product\Repositories\ProductBlurhashWriter;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductBlurhashWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_blur_hash_enrichment_is_narrow_and_tenant_scoped(): void
    {
        $owner = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $product = Product::factory()->forTenant($owner)->create([
            'image_path' => 'products/normalized.webp',
            'blur_hash' => null,
        ]);
        $writer = app(ProductBlurhashWriter::class);

        self::assertFalse($writer->updateBlurhash((string) $other->getKey(), (int) $product->getKey(), 'foreign'));
        self::assertNull($product->fresh()->blur_hash);
        self::assertTrue($writer->updateBlurhash((string) $owner->getKey(), (int) $product->getKey(), 'safe-hash'));
        self::assertSame('safe-hash', $product->fresh()->blur_hash);
    }
}
