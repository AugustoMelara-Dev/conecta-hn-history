<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateOrderStatusRequest - Form Request for Order Status Updates
 *
 * ARCHITECTURAL PURPOSE:
 * Encapsulates validation rules for order status transitions.
 */
final class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,processing,completed,cancelled'],
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
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado debe ser: pending, processing, completed o cancelled.',
        ];
    }
}
