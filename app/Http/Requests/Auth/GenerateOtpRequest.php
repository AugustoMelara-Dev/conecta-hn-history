<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GenerateOtpRequest - Form Request for OTP Generation
 *
 * ARCHITECTURAL PURPOSE:
 * Encapsulates validation rules for OTP generation request.
 * Includes custom identifier validation (email or international phone).
 */
final class GenerateOtpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public OTP generation endpoint
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
            'identifier' => ['required', 'string', 'max:255', $this->identifierFormatRule()],
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
     * Custom validation rule for identifier format.
     * Must be either a valid email or international phone number.
     */
    private function identifierFormatRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $isEmail = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            $isPhone = preg_match('/^\+[1-9]\d{1,14}$/', (string) $value) === 1;

            if (! $isEmail && ! $isPhone) {
                $fail('Debe ser un email válido o teléfono en formato internacional (+504...).');
            }
        };
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
            'identifier.required' => 'El identificador (email o teléfono) es obligatorio.',
            'identifier.max' => 'El identificador no puede exceder 255 caracteres.',
        ];
    }
}
