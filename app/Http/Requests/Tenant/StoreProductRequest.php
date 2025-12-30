<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreProductRequest - Product Creation Validation
 *
 * SECURITY: Tenant-scoped uniqueness for SKU.
 * Ensures no duplicate SKUs within the same tenant.
 */
final class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization handled in controller (tenant check)
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                // Tenant-scoped uniqueness
                Rule::unique('products', 'sku')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'is_visible' => ['nullable', 'boolean'],
            'stock_status' => ['nullable', 'string', Rule::in(['in_stock', 'low_stock', 'out_of_stock'])],
            'menu_section_id' => ['nullable', 'integer', 'exists:menu_sections,id'],
            'is_featured' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'], // 1MB max
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
            'name.min' => 'El nombre debe tener al menos :min caracteres.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número válido.',
            'price.min' => 'El precio no puede ser negativo.',
            'sku.unique' => 'Este código SKU ya está en uso.',
            'image.max' => 'La imagen no puede superar 1MB.',
            'image.mimes' => 'La imagen debe ser JPG, PNG o WebP.',
        ];
    }
}
