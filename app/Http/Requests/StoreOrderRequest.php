<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Application\Sales\DTOs\CreatePublicOrderData;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string|max:500',
            'customer_notes' => 'nullable|string|max:1000',
            'delivery_type' => 'required|in:pickup,delivery',
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'coupon_code' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(function ($query): void {
                    $query->where('is_visible', true)
                        ->where('is_active', true)
                        ->whereNull('deleted_at');
                }),
            ],
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.options' => 'nullable|array',
            'items.*.options.*.option_id' => 'nullable|integer',
            'items.*.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom error messages for validation failures.
     */
    public function messages(): array
    {
        return [
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
        $paymentMethod = $this->input('payment_method');

        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
            'payment_method' => $paymentMethod === 'transfer' ? PaymentMethod::BankTransfer->value : $paymentMethod,
        ]);

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
    public function toData(): CreatePublicOrderData
    {
        $validated = $this->validated();
        $tenantId = $this->tenantIdFromRequestedProducts($validated['items']);

        return new CreatePublicOrderData(
            tenantId: $tenantId,
            idempotencyKey: (string) $validated['idempotency_key'],
            customerName: $validated['customer_name'] ?? null,
            customerPhone: $validated['customer_phone'] ?? null,
            customerEmail: $validated['customer_email'] ?? null,
            customerAddress: $validated['customer_address'] ?? null,
            customerNotes: $validated['customer_notes'] ?? null,
            items: array_map(fn ($item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'options' => $item['options'] ?? [],
                'notes' => $item['notes'] ?? null,
            ], $validated['items']),
            deliveryType: $validated['delivery_type'],
            paymentMethod: $validated['payment_method'] ?? PaymentMethod::Pending->value,
            couponCode: $validated['coupon_code'] ?? null,
            userId: $this->user()?->getKey() ? (string) $this->user()->getKey() : null,
        );
    }

    /**
     * @param  array<int, array{product_id: int}>  $items
     */
    private function tenantIdFromRequestedProducts(array $items): string
    {
        $productIds = collect($items)->pluck('product_id')->unique()->values();
        $products = DB::table('products')
            ->join('tenants', 'tenants.id', '=', 'products.tenant_id')
            ->whereIn('products.id', $productIds)
            ->where('products.is_visible', true)
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->where('tenants.is_active', true)
            ->whereNull('tenants.publication_paused_at')
            ->pluck('products.tenant_id', 'products.id');

        if ($products->count() !== $productIds->count() || $products->unique()->count() !== 1) {
            throw ValidationException::withMessages([
                'items' => 'Todos los productos deben pertenecer al mismo negocio publicado.',
            ]);
        }

        return (string) $products->first();
    }
}
