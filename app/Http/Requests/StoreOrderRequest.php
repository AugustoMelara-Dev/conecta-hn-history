<?php

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
class StoreOrderRequest extends FormRequest
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
            'items.*.product_id' => 'nullable|exists:products,id',
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
}
