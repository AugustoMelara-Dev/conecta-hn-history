<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OptimizeProfileRequest - Strict Validation
 *
 * Enforces business rules for AI profile optimization.
 */
final class OptimizeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'target_audience' => ['required', 'string', 'max:255'],
            'keywords' => ['required', 'array', 'max:10'],
            'keywords.*' => ['string', 'max:50'],
            'tone' => ['required', 'string', Rule::in(['formal', 'casual', 'persuasive', 'witty', 'professional'])],
        ];
    }
}
