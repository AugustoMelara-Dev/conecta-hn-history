<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateOrderStatusRequest - Encapsulated Validation for Status Transitions
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SINGLE RESPONSIBILITY: Status validation rules isolated from Controller║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * BENEFITS:
 * - Controller stays clean and focused on orchestration
 * - Validation rules are reusable and testable
 * - Automatic 422 response on failure
 * - Consistent with StoreOrderRequest pattern
 */
class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled by tenant.ownership middleware.
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
            'status' => 'required|in:'.implode(',', Order::STATUSES),
        ];
    }

    /**
     * Get custom error messages for validation failures.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'El estado es requerido.',
            'status.in' => 'El estado proporcionado no es válido.',
        ];
    }
}
