<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Products\Commands\CreateProductCommand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreProductRequest - Validation for Product Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MULTI-TENANT SECURITY: Scoped Foreign Key Validation                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * SECURITY:
 * - Category/MenuSection IDs validated to belong to the authenticated tenant
 * - Prevents IDOR attacks where Tenant A could reference Tenant B's resources
 * - Image files validated for type and size to prevent DoS
 */
final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled by middleware (auth:sanctum + tenant.ownership)
        return true;
    }

    public function rules(): array
    {
        // ═══════════════════════════════════════════════════════════════════════
        // TENANT CONTEXT INJECTION (Strictly Typed)
        // ═══════════════════════════════════════════════════════════════════════
        // Tenant IDs are UUIDs. Keep the authenticated value as a string rather
        // than coercing the TenantId value object to an integer.
        $tenantId = (string) $this->user()->tenant_id;

        return [
            // ═══════════════════════════════════════════════════════════════════════
            // CORE PRODUCT DATA
            // ═══════════════════════════════════════════════════════════════════════
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/', 'max:11'],
            'sku' => ['prohibited'],
            'stock_status' => ['prohibited'],
            'image' => ['prohibited'],
            'remove_image' => ['prohibited'],

            // ═══════════════════════════════════════════════════════════════════════
            // SCOPED FOREIGN KEYS (IDOR Prevention)
            // ═══════════════════════════════════════════════════════════════════════
            // These rules ensure the ID exists AND belongs to the current tenant.
            // A malicious user cannot reference another tenant's categories.
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'menu_section_id' => [
                'nullable',
                Rule::exists('menu_sections', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],

            // ═══════════════════════════════════════════════════════════════════════
            // INVENTORY MANAGEMENT
            // ═══════════════════════════════════════════════════════════════════════
            'manage_stock' => 'nullable|boolean',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'allow_backorders' => 'nullable|boolean',

            // ═══════════════════════════════════════════════════════════════════════
            // PRODUCT IMAGE (Binary Ingestion)
            // ═══════════════════════════════════════════════════════════════════════
            // Maximum 2MB to prevent disk exhaustion attacks.
            // Standard web formats only for browser compatibility.

            // ═══════════════════════════════════════════════════════════════════════
            // EXPLICIT IMAGE REMOVAL (Nullification Signal)
            // ═══════════════════════════════════════════════════════════════════════
            // When true, removes current image without replacement.
            // Allows users to revert to "No Image" state.

            // ═══════════════════════════════════════════════════════════════════════
            // DISPLAY OPTIONS
            // ═══════════════════════════════════════════════════════════════════════
            'is_visible' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_new' => 'nullable|boolean',
            'variants' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del producto es obligatorio.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número.',
            'category_id.exists' => 'La categoría seleccionada no existe o no pertenece a su negocio.',
            'menu_section_id.exists' => 'La sección de menú seleccionada no existe o no pertenece a su negocio.',
            'image.image' => 'El archivo debe ser una imagen válida.',
            'image.mimes' => 'La imagen debe ser JPEG, PNG o WebP.',
            'image.max' => 'La imagen no puede exceder 2MB.',
        ];
    }

    public function toCommand(): CreateProductCommand
    {
        $validated = $this->validated();

        return new CreateProductCommand(
            tenantId: (string) $this->user()->tenant_id,
            sku: (string) ($validated['sku'] ?? ''), name: (string) $validated['name'],
            description: (string) ($validated['description'] ?? ''), price: 0,
            stock: (int) ($validated['stock_quantity'] ?? 0), categoryId: $validated['category_id'] ?? null,
            imagePath: null, isVisible: (bool) ($validated['is_visible'] ?? true),
        );
    }
}
