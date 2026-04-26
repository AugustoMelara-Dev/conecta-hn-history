<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Shared\Enums\ProviderName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SocialLoginRequest extends FormRequest
{
    private const MAX_TOKEN_LENGTH = 4096;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => [
                'required',
                'string',
                'max:50',
                Rule::in(self::getSupportedProviders()),
            ],
            'token' => [
                'required',
                'string',
                'max:'.self::MAX_TOKEN_LENGTH,
            ],

            // Never trust client-supplied identity claims for social auth.
            // They must be derived from the provider token server-side.
            'provider_id' => ['prohibited'],
            'email' => ['prohibited'],
            'name' => ['prohibited'],
            'avatar' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $supportedList = implode(', ', self::getSupportedProviders());

        return [
            'provider.in' => "El proveedor de autenticacion no es soportado. Proveedores validos: {$supportedList}.",
            'token.max' => 'El token de autenticacion excede el limite maximo permitido.',
            'provider_id.prohibited' => 'El identificador del proveedor se obtiene directamente del proveedor OAuth.',
            'email.prohibited' => 'El correo se obtiene directamente del proveedor OAuth verificado.',
            'name.prohibited' => 'El nombre se obtiene directamente del proveedor OAuth verificado.',
            'avatar.prohibited' => 'El avatar se obtiene directamente del proveedor OAuth verificado.',
        ];
    }

    /**
     * @return array{provider: string, token: string}
     */
    public function validatedData(): array
    {
        return $this->validated();
    }

    /**
     * @return array<string>
     */
    public static function getSupportedProviders(): array
    {
        return array_map(
            static fn (ProviderName $provider): string => $provider->value,
            ProviderName::cases()
        );
    }
}
