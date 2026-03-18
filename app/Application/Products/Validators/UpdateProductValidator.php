<?php

declare(strict_types=1);

namespace App\Application\Products\Validators;

use App\Application\Products\Commands\UpdateProductCommand;
use App\Application\Products\Validators\Rules\UniqueProductSkuRule;
use App\Domain\Product\Repositories\ProductRepositoryInterface;

/**
 * UpdateProductValidator - Structural Validation
 */
final readonly class UpdateProductValidator
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {}

    public function rules(UpdateProductCommand $command): array
    {
        return [
            'sku' => [
                'required',
                'string',
                'max:100',
                new UniqueProductSkuRule($this->productRepository, (string) $command->productId),
            ],
            // Add other fields if command has them (Assuming Command mirrors params)
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
