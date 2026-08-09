<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'uuid'],
            'identifier' => ['required', 'string', 'max:255'],
            'otp_code' => ['required', 'numeric', 'digits:6'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tenant_id' => $this->header('X-Tenant-ID'),
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Debes indicar el negocio.',
            'tenant_id.uuid' => 'El identificador del negocio no es válido.',
            'idempotency_key.required' => 'Debes enviar una clave de idempotencia.',
            'idempotency_key.uuid' => 'La clave de idempotencia debe ser un UUID.',
            'identifier.required' => 'El email o teléfono es obligatorio.',
            'otp_code.required' => 'El código OTP es obligatorio.',
            'otp_code.numeric' => 'El código OTP debe ser numérico.',
            'otp_code.digits' => 'El código OTP debe tener exactamente 6 dígitos.',
        ];
    }
}
