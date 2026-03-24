<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Analytics\Enums\InteractionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * StoreInteractionRequest - Security Gate for Analytics
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ANTI-ENUMERATION STRATEGY:                                              ║
 * ║  We intentionally DO NOT use 'exists:tenants,id' here.                   ║
 * ║  Returning 422 for invalid IDs indicates which IDs are valid/invalid     ║
 * ║  to an attacker. Verification happens SILENTLY in the Controller.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class StoreInteractionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $type = (string) $this->input('type', '');

        $this->merge([
            'type' => match ($type) {
                'call_click' => InteractionType::CALL->value,
                'maps_click' => InteractionType::LOCATION_VIEW->value,
                default => $type,
            },
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Analytics are public
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'string', 'max:64'],
            'type' => ['required', 'string', Rule::in(InteractionType::values())],
            'product_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
