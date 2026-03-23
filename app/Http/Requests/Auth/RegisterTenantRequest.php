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

    protected function prepareForValidation(): void
    {
        $businessName = $this->input('business_name', $this->input('name'));
        $ownerName = $this->input('owner_name', $this->input('name'));

        $this->merge([
            'business_name' => $businessName,
            'name' => $ownerName,
            'slug' => $this->filled('slug')
                ? $this->input('slug')
                : \Illuminate\Support\Str::slug((string) $businessName),
            'whatsapp' => $this->input('whatsapp', $this->input('whatsapp_number')),
            'selected_plan' => $this->input('selected_plan', $this->input('plan', 'free')),
        ]);
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
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2000,max_height=2000'],

            // Step 4: Plan Selection
            'selected_plan' => ['nullable', 'in:free,pro'],
            'terms_accepted' => ['required', 'accepted'],
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
            'category_id.required' => 'Selecciona una categoría.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',
            'selected_plan.in' => 'El plan seleccionado no es válido.',
            'terms_accepted.accepted' => 'Debes aceptar los términos para continuar.',
        ];
    }
}
