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
            'city_id' => ['nullable', 'required_without:locality_id', 'integer', 'exists:cities,id'],
            'country_code' => [
                'nullable',
                'required_without:city_id',
                'string',
                'size:2',
                Rule::exists('countries', 'code')->where('is_active', true),
            ],
            'locality_id' => [
                'nullable',
                'required_without:city_id',
                'integer',
                Rule::exists('localities', 'id')->where('is_active', true),
            ],
            'locale_code' => ['nullable', 'required_without:city_id', 'string', 'max:16', 'exists:locales,code'],
            'currency_code' => ['nullable', 'required_without:city_id', 'string', 'size:3', 'exists:currencies,code'],
            'timezone' => ['nullable', 'required_without:city_id', 'string', 'max:64', 'exists:timezones,name'],
            'address' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'business_type' => ['nullable', 'in:retail,service'],
            'commercial_mode' => [
                'required',
                Rule::in([
                    CommercialMode::PRODUCTS->value,
                    CommercialMode::APPOINTMENTS->value,
                    CommercialMode::CONSULTING_LEADS->value,
                    CommercialMode::HYBRID->value,
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
            'business_type.in' => 'La operación seleccionada no es válida.',
            'commercial_mode.required' => 'Selecciona cómo quieres recibir clientes.',
            'commercial_mode.in' => 'La ruta comercial seleccionada no está disponible.',
            'selected_plan.required' => 'Selecciona un plan.',
            'selected_plan.in' => 'El plan seleccionado no es válido.',
            'terms_accepted.required' => 'Debes aceptar los términos para continuar.',
            'terms_accepted.accepted' => 'Debes aceptar los términos para continuar.',
            'privacy_accepted.required' => 'Debes aceptar la política de privacidad para continuar.',
            'privacy_accepted.accepted' => 'Debes aceptar la política de privacidad para continuar.',
        ];
    }
}
