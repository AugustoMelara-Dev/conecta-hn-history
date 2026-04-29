<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * VerifyOtpRequest - Form Request for OTP Verification
 *
 * ARCHITECTURAL PURPOSE:
 * Encapsulates validation rules for OTP verification.
 * Controllers MUST NOT contain inline validation logic.
 *
 * SCOPE: Format/type validation ONLY.
 * Business logic (expiration, attempts) belongs in VerifyOtpAction.
 */
final class VerifyOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $identifier = $this->input('identifier', $this->input('uuid'));
        $otpCode = $this->input('otp_code', $this->input('code'));

        $this->merge([
            'identifier' => is_string($identifier) ? $identifier : $this->input('identifier'),
            'otp_code' => is_string($otpCode) ? $otpCode : $this->input('otp_code'),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public OTP verification endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'otp_code' => ['required', 'numeric', 'digits:6'],
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
            'identifier.required' => 'El email o telefono es obligatorio.',
            'identifier.max' => 'El identificador no puede exceder 255 caracteres.',
            'otp_code.required' => 'El código OTP es obligatorio.',
            'otp_code.numeric' => 'El código OTP debe ser numérico.',
            'otp_code.digits' => 'El código OTP debe tener exactamente 6 dígitos.',
        ];
    }
}
