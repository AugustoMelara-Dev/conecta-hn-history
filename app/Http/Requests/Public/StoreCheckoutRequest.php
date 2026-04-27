<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

final class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
            'location_id' => ['nullable', 'string', 'uuid'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'delivery_type' => ['required', 'in:pickup,delivery'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'session_id' => ['nullable', 'string', 'max:255'],
            'checkout_nonce' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('coupon_code') && $this->coupon_code) {
            $this->merge([
                'coupon_code' => strtoupper(trim((string) $this->coupon_code)),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenantId = $this->input('tenant_id');
            if (! is_string($tenantId) || $tenantId === '') {
                return;
            }

            $productIds = collect($this->input('items', []))
                ->pluck('product_id')
                ->filter(static fn (mixed $id): bool => is_numeric($id))
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();

            if ($productIds->isEmpty()) {
                return;
            }

            $ownedCount = DB::table('products')
                ->whereIn('id', $productIds->all())
                ->where('tenant_id', $tenantId)
                ->count();

            if ($ownedCount !== $productIds->count()) {
                $validator->errors()->add('items', 'Todos los productos deben pertenecer al negocio seleccionado.');
            }
        });
    }
}
