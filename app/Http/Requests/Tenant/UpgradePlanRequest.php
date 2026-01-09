<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpgradePlanRequest - Validation for Plan Upgrades
 */
final class UpgradePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', 'in:pro,premium'],
            'payment_method' => ['required', 'string'],
        ];
    }
}
