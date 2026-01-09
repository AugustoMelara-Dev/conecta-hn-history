<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ReportPaymentRequest - Validation for Manual Payment Reporting
 */
final class ReportPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank' => ['required', 'string', 'in:bac,ficohsa,banpais,atlantida,davivienda,occidente,other'],
            'reference_number' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:1'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }
}
