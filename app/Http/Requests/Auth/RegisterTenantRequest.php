<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

/**
 * RegisterTenantRequest - Form Request for User + Tenant Registration
 *
 * ARCHITECTURAL PURPOSE:
 * Encapsulates ALL validation rules for the registration flow.
 * Controllers MUST NOT contain inline validation logic.
 *
 * SECURITY: Validation runs BEFORE controller is invoked.
 */
final class RegisterTenantRequest extends FormRequest
{
    /**
     * Reserved slugs that conflict with system routes.
     */
    private const RESERVED_SLUGS = [
        'admin', 'login', 'register', 'billing', 'api', 'dashboard',
        'profile', 'settings', 'logout', 'password', 'forgot-password',
        'reset-password', 'verify-email', 'email', 'confirm-password',
        'directorio', 'ciudad', 'categoria', 'ciudades', 'explorar',
        'registrar-negocio', 'payment', 'storage', 'css', 'js', 'fonts',
        'images', 'assets', 'public', 'static', 'uploads', 'media',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public registration endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Step 1: Identity Claim
            'business_name' => ['required', 'string', 'max:255', 'min:3'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'min:3',
                'alpha_dash',
                'unique:tenants,slug',
                'not_in:'.implode(',', self::RESERVED_SLUGS),
            ],

            // Step 2: User Credentials
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            // Step 3: Business Profile
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'commercial_mode' => ['required', 'string', 'in:products,appointments,consulting_leads'],
            'business_type' => ['nullable', 'string', 'in:retail,service'],
            'rubro_key' => ['nullable', 'string', 'max:80'],
            'whatsapp' => ['nullable', 'string', 'max:20'],

            // Step 4: Plan Selection
            'selected_plan' => ['required', 'in:free,pro'],
            'terms_accepted' => ['required', 'accepted'],
            'privacy_accepted' => ['required', 'accepted'],
            'terms_version' => ['nullable', 'string', 'max:40'],
            'privacy_version' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_name.required' => 'Ingresa el nombre de tu negocio.',
            'business_name.min' => 'El nombre debe tener al menos 3 caracteres.',
            'slug.required' => 'La URL de tu negocio es obligatoria.',
            'slug.alpha_dash' => 'La URL solo puede contener letras, números y guiones.',
            'slug.unique' => 'Esta URL ya está siendo usada por otro negocio.',
            'slug.min' => 'La URL debe tener al menos 3 caracteres.',
            'slug.not_in' => 'Esta URL está reservada y no puede ser utilizada.',
            'name.required' => 'Ingresa tu nombre completo.',
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Este correo ya está registrado.',
            'password.required' => 'Ingresa una contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'city_id.required' => 'Selecciona una ciudad.',
            'city_id.exists' => 'La ciudad seleccionada no es válida.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',
            'commercial_mode.required' => 'Selecciona como quieres recibir clientes.',
            'commercial_mode.in' => 'Selecciona una ruta comercial válida.',
            'selected_plan.required' => 'Selecciona un plan.',
            'selected_plan.in' => 'El plan seleccionado no es válido.',
            'terms_accepted.accepted' => 'Debes aceptar los términos para continuar.',
            'privacy_accepted.accepted' => 'Debes aceptar la política de privacidad para continuar.',
        ];
    }
}
