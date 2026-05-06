<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SocialLoginRequest - Security-Hardened FormRequest for Social Authentication
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY MANDATE: DoS Prevention via Strict Input Validation           ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  VULNERABILITIES MITIGATED:                                             ║
 * ║    1. Memory Exhaustion: max:4096 on token field                        ║
 * ║    2. Injection: Rule::in() whitelist on provider                       ║
 * ║    3. Buffer Overflow: max:255 on all string fields                     ║
 * ║                                                                          ║
 * ║  ARCHITECTURAL COMPLIANCE:                                               ║
 * ║    - Hexagonal: Validation at Application Boundary                      ║
 * ║    - DDD: Strict Typing via validated() output                          ║
 * ║    - SOLID: Single Responsibility for Social Auth validation            ║
 * ║                                                                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class SocialLoginRequest extends FormRequest
{
    /**
     * Supported OAuth providers (whitelist).
     * 
     * SECURITY: Only explicitly supported providers are allowed.
     * Adding a new provider requires code change (intentional friction).
     */
    private const SUPPORTED_PROVIDERS = [
        'google',
        'facebook',
        'apple',
        'github',
        'twitter',
    ];

    /**
     * Maximum token length to prevent memory exhaustion attacks.
     * 
     * OAuth tokens typically range 100-2000 characters.
     * 4096 provides buffer for future expansion while blocking DoS.
     */
    private const MAX_TOKEN_LENGTH = 4096;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool Always true - public authentication endpoint.
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
            // ═══════════════════════════════════════════════════════════════════
            // PROVIDER: Whitelist validation (prevents arbitrary service injection)
            // ═══════════════════════════════════════════════════════════════════
            'provider' => [
                'required',
                'string',
                'max:50',
                Rule::in(self::SUPPORTED_PROVIDERS),
            ],

            // ═══════════════════════════════════════════════════════════════════
            // TOKEN: OAuth access token from provider
            // SECURITY: max:4096 prevents memory exhaustion DoS attacks
            // ═══════════════════════════════════════════════════════════════════
            'token' => [
                'required',
                'string',
                'max:' . self::MAX_TOKEN_LENGTH,
            ],

            // ═══════════════════════════════════════════════════════════════════
            // PROVIDER_ID: Unique identifier from OAuth provider
            // ═══════════════════════════════════════════════════════════════════
            'provider_id' => [
                'required',
                'string',
                'max:255',
            ],

            // ═══════════════════════════════════════════════════════════════════
            // EMAIL: User email from OAuth payload (RFC 5321 + DNS validation)
            // ═══════════════════════════════════════════════════════════════════
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
            ],

            // ═══════════════════════════════════════════════════════════════════
            // NAME: Display name from OAuth profile
            // ═══════════════════════════════════════════════════════════════════
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],

            // ═══════════════════════════════════════════════════════════════════
            // AVATAR: Profile image URL
            // ═══════════════════════════════════════════════════════════════════
            'avatar' => [
                'nullable',
                'url',
                'max:2048',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $supportedList = implode(', ', self::SUPPORTED_PROVIDERS);

        return [
            'provider.in' => "El proveedor de autenticación no es soportado. Proveedores válidos: {$supportedList}.",
            'token.max' => 'El token de autenticación excede el límite máximo permitido.',
            'provider_id.required' => 'El identificador del proveedor es requerido.',
            'email.email' => 'El correo electrónico proporcionado no es válido.',
            'email.required' => 'El correo electrónico es requerido para autenticación social.',
        ];
    }

    /**
     * Get the validated data with type safety.
     *
     * @return array{
     *     provider: string,
     *     token: string,
     *     provider_id: string,
     *     email: string,
     *     name: ?string,
     *     avatar: ?string
     * }
     */
    public function validatedData(): array
    {
        return $this->validated();
    }

    /**
     * Get supported providers list (for external reference).
     *
     * @return array<string>
     */
    public static function getSupportedProviders(): array
    {
        return self::SUPPORTED_PROVIDERS;
    }
}
