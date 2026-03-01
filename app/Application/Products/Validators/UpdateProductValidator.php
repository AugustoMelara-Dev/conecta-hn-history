<?php

declare(strict_types=1);

namespace App\Application\Validators\Products;

use App\Application\Products\Commands\UpdateProductCommand;
use Illuminate\Validation\Rule;

/**
 * UpdateProductValidator - Structural Validation
 */
final readonly class UpdateProductValidator
{
    public function rules(UpdateProductCommand $command): array
    {
        return [
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products')->ignore($command->productId),
            ],
            // Add other fields if command has them (Assuming Command mirrors params)
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
