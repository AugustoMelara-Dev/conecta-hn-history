<?php

declare(strict_types=1);

namespace App\Domain\Product\Exceptions;

use RuntimeException;

final class ProductNotFoundException extends RuntimeException
{
    public static function withId(int $productId): self
    {
        return new self("Product not found: {$productId}");
    }
}
