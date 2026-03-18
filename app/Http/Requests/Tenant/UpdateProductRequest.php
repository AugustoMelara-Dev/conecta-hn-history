<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Application\Products\Commands\UpdateProductCommand;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateProductRequest - Product Update Validation
 *
 * SECURITY: Tenant-scoped uniqueness for SKU.
 * Ignores the current product when checking uniqueness.
 */
final class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->user();
        $tenantId = $user?->tenant_id;
        $product = $this->route('product');
        $productId = $product instanceof Product ? (int) $product->getKey() : (int) $product;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                // Tenant-scoped uniqueness, ignoring current product
                Rule::unique('products', 'sku')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->ignore($productId),
            ],
            'is_visible' => ['nullable', 'boolean'],
            'stock_status' => ['nullable', 'string', Rule::in(['in_stock', 'low_stock', 'out_of_stock'])],
            'menu_section_id' => ['nullable', 'integer', 'exists:menu_sections,id'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del producto',
            'price' => 'precio',
            'description' => 'descripción',
            'sku' => 'código SKU',
            'image' => 'imagen',
            'menu_section_id' => 'categoría',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del producto es obligatorio.',
            'image.max' => 'La imagen no puede superar 1MB.',
        ];
    }

    public function toCommand(int $productId): UpdateProductCommand
    {
        /** @var User|null $user */
        $user = $this->user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Authenticated user required to update products.');
        }

        $validated = $this->validated();
        $headerKey = $this->header('Idempotency-Key');
        if (! is_string($headerKey) || $headerKey === '') {
            $headerKey = $this->header('X-Idempotency-Key');
        }

        $idempotencyKey = is_string($headerKey) && $headerKey !== ''
            ? $headerKey
            : hash('xxh128', json_encode([
                'tenant_id' => (string) $user->getTenantId(),
                'product_id' => $productId,
                'name' => $validated['name'],
                'sku' => $validated['sku'] ?? null,
                'price' => $validated['price'],
            ], JSON_THROW_ON_ERROR));

        return new UpdateProductCommand(
            tenantId: (string) $user->getTenantId(),
            productId: $productId,
            name: $validated['name'],
            description: $validated['description'] ?? '',
            price: (int) round((float) $validated['price'] * 100),
            idempotencyKey: $idempotencyKey,
            sku: isset($validated['sku']) ? (string) $validated['sku'] : null,
            stock: isset($validated['stock']) ? (int) $validated['stock'] : 0,
            categoryId: isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            imagePath: null,
            isVisible: (bool) ($validated['is_visible'] ?? true),
        );
    }
}
