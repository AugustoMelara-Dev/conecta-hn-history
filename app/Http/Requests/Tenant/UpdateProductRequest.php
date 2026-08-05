<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

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
        $tenantId = (string) $this->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'price' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/', 'max:11'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'allow_backorders' => ['sometimes', 'boolean'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
            'menu_section_id' => ['nullable', 'integer', Rule::exists('menu_sections', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'manage_stock' => ['sometimes', 'boolean'],
            'variants' => ['sometimes', 'array'],
            'sku' => ['prohibited'],
            'stock_status' => ['prohibited'],
            'image' => ['prohibited'],
            'remove_image' => ['prohibited'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
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

    public function toCommand(int $productId): \App\Application\Products\Commands\UpdateProductCommand
    {
        $validated = $this->validated();

        return new \App\Application\Products\Commands\UpdateProductCommand(
            tenantId: (string) $this->user()->tenant_id, productId: $productId,
            name: (string) $validated['name'], description: (string) ($validated['description'] ?? ''),
            price: 0, stock: (int) ($validated['stock_quantity'] ?? 0),
            categoryId: $validated['category_id'] ?? null, imagePath: null,
            isVisible: (bool) ($validated['is_visible'] ?? true),
        );
    }
}
