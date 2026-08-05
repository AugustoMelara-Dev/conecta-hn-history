<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Product;
use Tests\TestCase;

final class ProductImageUrlTest extends TestCase
{
    public function test_published_product_image_url_uses_the_public_disk(): void
    {
        config([
            'filesystems.default' => 'local',
            'filesystems.disks.public.url' => 'https://cdn.conecta.test/storage',
        ]);
        $product = new Product;
        $product->forceFill(['image_path' => 'products/tenant-1/taza.png']);

        self::assertSame(
            'https://cdn.conecta.test/storage/products/tenant-1/taza.png',
            $product->image_url,
        );
    }
}
