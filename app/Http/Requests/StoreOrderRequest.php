<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreOrderRequest - Encapsulated Validation for Order Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SINGLE RESPONSIBILITY: Validation rules isolated from Controller       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * BENEFITS:
 * - Controller stays clean and focused on orchestration
 * - Validation rules are reusable and testable
 * - Automatic 422 response on failure
 * - Type-safe validated() data access
 */
final class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * For public checkout flow, always return true.
     * Add authentication checks here if needed.
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
        return [
            'tenant_id' => 'required|exists:tenants,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string|max:500',
            'customer_notes' => 'nullable|string|max:1000',
            'delivery_type' => 'required|in:pickup,delivery',
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'coupon_code' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.options' => 'nullable|array',
            'items.*.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'tenant_id.required' => 'El ID del negocio es requerido.',
            'tenant_id.exists' => 'El negocio especificado no existe.',
            'customer_name.required' => 'El nombre del cliente es requerido.',
            'customer_phone.required' => 'El teléfono del cliente es requerido.',
            'items.required' => 'El pedido debe contener al menos un producto.',
            'items.min' => 'El pedido debe contener al menos un producto.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Normalizes input before validation runs.
     */
    protected function prepareForValidation(): void
    {
        // Normalize coupon code to uppercase
        if ($this->has('coupon_code') && $this->coupon_code) {
            $this->merge([
                'coupon_code' => strtoupper(trim($this->coupon_code)),
            ]);
        }
    }

    /**
     * Factory: Create Command from Validated Data.
     */
    public function toCommand(): \App\Application\Sales\Commands\CreateOrderCommand
    {
        // 1. Get validated data (clean, type-safe)
        $validated = $this->validated();

        // 2. Map HTTP Input (snake_case) to Domain Command (camelCase)
        return new \App\Application\Sales\Commands\CreateOrderCommand(
            tenantId: (int) $validated['tenant_id'],
            customerId: isset($validated['customer_id']) ? (int) $validated['customer_id'] : null, // Handle conditional ID
            customerName: $validated['customer_name'] ?? null,
            customerEmail: $validated['customer_email'] ?? null,
            customerPhone: $validated['customer_phone'] ?? null,
            customerAddress: $validated['customer_address'] ?? null,
            customerNotes: $validated['customer_notes'] ?? null,
            currency: 'HNL', // Default or read from request if supported
            items: array_map(fn ($item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['price'],
                'options' => $item['options'] ?? [],
                'notes' => $item['notes'] ?? null,
            ], $validated['items']),
            deliveryType: $validated['delivery_type'],
            paymentMethod: $validated['payment_method'] ?? null,
            couponCode: $validated['coupon_code'] ?? null,
            requestorUserId: $this->user()?->id, // Nullable for guests
        );
    }
}
