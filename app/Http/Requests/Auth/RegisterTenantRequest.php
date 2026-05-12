<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\TenantGovernance\Enums\CommercialMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

final class RegisterTenantRequest extends FormRequest
{
    private const RESERVED_SLUGS = [
        'admin', 'login', 'register', 'billing', 'api', 'dashboard',
        'profile', 'settings', 'logout', 'password', 'forgot-password',
        'reset-password', 'verify-email', 'email', 'confirm-password',
        'directorio', 'ciudad', 'categoria', 'ciudades', 'explore',
        'registrar-negocio', 'payment', 'storage', 'css', 'js', 'fonts',
        'images', 'assets', 'public', 'static', 'uploads', 'media',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mode = CommercialMode::tryFrom((string) $this->input('commercial_mode'));

        if (! $mode instanceof CommercialMode) {
            return;
        }

        $this->merge([
            'commercial_mode' => $mode->value,
            'business_type' => $mode->businessType()->value,
        ]);
    }

    public function rules(): array
    {
        return [
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'business_type' => ['nullable', 'in:retail,service'],
            'commercial_mode' => [
                'required',
                Rule::in([
                    CommercialMode::PRODUCTS->value,
                    CommercialMode::APPOINTMENTS->value,
                    CommercialMode::CONSULTING_LEADS->value,
                ]),
            ],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'selected_plan' => ['required', 'in:free,pro'],
            'terms_accepted' => ['required', 'accepted'],
            'privacy_accepted' => ['required', 'accepted'],
            'terms_version' => ['nullable', 'string', 'max:30'],
            'privacy_version' => ['nullable', 'string', 'max:30'],
            'rubro_key' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required' => 'Ingresa el nombre de tu negocio.',
            'business_name.min' => 'El nombre debe tener al menos 3 caracteres.',
            'slug.required' => 'La URL de tu negocio es obligatoria.',
            'slug.alpha_dash' => 'La URL solo puede contener letras, numeros y guiones.',
            'slug.unique' => 'Esta URL ya esta siendo usada por otro negocio.',
            'slug.min' => 'La URL debe tener al menos 3 caracteres.',
            'slug.not_in' => 'Esta URL esta reservada y no puede ser utilizada.',
            'name.required' => 'Ingresa tu nombre completo.',
            'email.required' => 'Ingresa tu correo electronico.',
            'email.email' => 'Ingresa un correo electronico valido.',
            'email.unique' => 'Este correo ya esta registrado.',
            'password.required' => 'Ingresa una contrasena.',
            'password.confirmed' => 'Las contrasenas no coinciden.',
            'city_id.required' => 'Selecciona una ciudad.',
            'city_id.exists' => 'La ciudad seleccionada no es valida.',
            'category_id.exists' => 'La categoria seleccionada no es valida.',
            'business_type.in' => 'La operacion seleccionada no es valida.',
            'commercial_mode.required' => 'Selecciona como quieres recibir clientes.',
            'commercial_mode.in' => 'La ruta comercial seleccionada no esta disponible.',
            'selected_plan.required' => 'Selecciona un plan.',
            'selected_plan.in' => 'El plan seleccionado no es valido.',
            'terms_accepted.required' => 'Debes aceptar los terminos para continuar.',
            'terms_accepted.accepted' => 'Debes aceptar los terminos para continuar.',
            'privacy_accepted.required' => 'Debes aceptar la politica de privacidad para continuar.',
            'privacy_accepted.accepted' => 'Debes aceptar la politica de privacidad para continuar.',
        ];
    }
}
