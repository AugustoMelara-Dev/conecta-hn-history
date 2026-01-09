<?php

declare(strict_types=1);

namespace App\Application\Validators\Products;

use App\Application\Products\Commands\CreateProductCommand;

/**
 * CreateProductValidator - Structural Validation Rules
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STRUCTURAL ONLY: Validates format, not business feasibility.           ║
 * ║  UNIQUE RULE: Pragmatically included here for SKU/name collision.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class CreateProductValidator
{
    /**
     * Get the validation rules that apply to the command.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(CreateProductCommand $command): array
    {
        return [
            'tenantId' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'imagePath' => ['nullable', 'string', 'max:500'],
            'isVisible' => ['nullable', 'boolean'],
        ];
    }
}
