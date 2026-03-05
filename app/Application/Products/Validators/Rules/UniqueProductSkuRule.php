<?php

declare(strict_types=1);

namespace App\Application\Products\Validators\Rules;

use App\Application\Shared\Contracts\ValidationRuleInterface;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use Closure;

final readonly class UniqueProductSkuRule implements ValidationRuleInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private string $ignoreProductId
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->productRepository->existsBySkuExcludingId($value, $this->ignoreProductId)) {
            $fail('The :attribute has already been taken.');
        }
    }
}
