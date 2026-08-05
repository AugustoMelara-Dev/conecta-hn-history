<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateTenantSettingsRequest - Secure Tenant Configuration Validation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURE CONFIGURATION TUNNEL: No tenant_id in request payload          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * SECURITY MODEL:
 * - Tenant is determined SOLELY via $request->user()->tenant
 * - No ID manipulation possible (IDOR prevention)
 * - All fields are optional for partial updates
 *
 * DATA INTEGRITY:
 * - String length limits prevent buffer/column overflow
 * - Phone number format validation for WhatsApp
 */
final class UpdateTenantSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Users can only update their OWN tenant settings.
     * Authorization is implicit: we pull tenant from authenticated user.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // ═══════════════════════════════════════════════════════════════════════
            // BANKING INFORMATION (Manual Payment Workflow)
            // ═══════════════════════════════════════════════════════════════════════
            'bank_name' => 'nullable|string|max:50',
            'bank_account' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_account_type' => 'nullable|string|in:ahorro,corriente',

            // ═══════════════════════════════════════════════════════════════════════
            // WHATSAPP CONFIGURATION
            // ═══════════════════════════════════════════════════════════════════════
            // Strict regex validation prevents garbage input like "Solo llamadas".
            // Allows: +504 9999-9999, 9999 9999, (504)99999999, etc.
            // Rejects: Alphabetic text, arbitrary descriptions.
            //
            // Pattern breakdown:
            // ^[\+]?     - Optional plus sign for international format
            // [(]?[0-9]{1,4}[)]?  - Optional country code with optional parens (1-4 digits)
            // [-\s\.]?   - Optional separator (dash, space, dot)
            // [0-9]{1,4} - Next number group (1-4 digits)
            // [-\s\.]?   - Optional separator
            // [0-9]{4,8}$ - Final digits (4-8 to support local/international)
            'whatsapp_number' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,4}[-\s\.]?[0-9]{4,8}$/',
            ],

            // ═══════════════════════════════════════════════════════════════════════
            // LOGO UPLOAD (Branding Asset)
            // ═══════════════════════════════════════════════════════════════════════
            // Maximum 2MB to prevent disk exhaustion attacks.
            // Standard web formats only for browser compatibility.
            'logo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048', // 2MB in KB
            ],

            // ═══════════════════════════════════════════════════════════════════════
            // EXPLICIT LOGO REMOVAL (Nullification Signal)
            // ═══════════════════════════════════════════════════════════════════════
            // When true, deletes current logo without replacement.
            // Allows tenants to revert to "No Logo" state.
            'remove_logo' => 'nullable|boolean',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'bank_name.max' => 'El nombre del banco no puede exceder 50 caracteres.',
            'bank_account.max' => 'El número de cuenta no puede exceder 50 caracteres.',
            'bank_account_name.max' => 'El nombre del beneficiario no puede exceder 100 caracteres.',
            'bank_account_type.in' => 'El tipo de cuenta debe ser "ahorro" o "corriente".',
            'whatsapp_number.max' => 'El número de WhatsApp no puede exceder 20 caracteres.',
            'whatsapp_number.regex' => 'El número de WhatsApp debe contener solo dígitos y formato válido (ej: +504 9999-9999).',
        ];
    }
}
