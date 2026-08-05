<?php

declare(strict_types=1);

namespace App\Application\Products;

final readonly class ProductImagePromotion
{
    public function __construct(
        public string $publishedPath,
        public string $sourceDigest,
    ) {}
}
