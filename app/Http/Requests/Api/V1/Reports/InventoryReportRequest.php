<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Reports;

use Illuminate\Foundation\Http\FormRequest;

/**
 * InventoryReportRequest - Strict Validation for Inventory Report
 *
 * ARCHITECTURE: Input Hardening
 * - Minimal validation (inventory is point-in-time)
 * - Authorization enforced
 */
final class InventoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Inventory reports are point-in-time, no date filtering needed
            // Future: could add category_id, low_stock_only, etc.
        ];
    }
}
