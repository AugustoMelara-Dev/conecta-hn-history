<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreInteractionRequest - Security Gate for Analytics
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ANTI-ENUMERATION STRATEGY:                                              ║
 * ║  We intentionally DO NOT use 'exists:tenants,id' here.                   ║
 * ║  Returning 422 for invalid IDs indicates which IDs are valid/invalid     ║
 * ║  to an attacker. Verification happens SILENTLY in the Controller.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class StoreInteractionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Analytics are public
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer'],
            // Allowlist for event types to prevent Garbage Injection
            'type' => ['required', 'string', 'in:view,whatsapp_click,call_click,maps_click'],
            'product_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
