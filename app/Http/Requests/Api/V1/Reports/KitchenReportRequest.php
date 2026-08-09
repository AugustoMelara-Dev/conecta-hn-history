<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Reports;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * KitchenReportRequest - Strict Validation for Kitchen Prep List
 *
 * ARCHITECTURE: Input Hardening
 * - Authorization enforced
 * - Minimal validation for operational report
 */
final class KitchenReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Kitchen prep lists are real-time, no date filtering
            // Future: could add shift, category, priority filters
        ];
    }
}
