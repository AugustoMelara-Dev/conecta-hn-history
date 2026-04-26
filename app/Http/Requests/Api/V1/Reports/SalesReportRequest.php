<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Reports;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\RequestTenantResolver;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SalesReportRequest - Strict Validation for Sales Report Generation
 *
 * ARCHITECTURE: Input Hardening
 * - All date filters validated
 * - Status enum enforced
 * - Controller receives guaranteed-valid data
 */
final class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $tenant = app(RequestTenantResolver::class)->resolve($this);

        if (! $tenant instanceof Tenant) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return app(TenantPermissionGuardInterface::class)->allows(
            $user,
            $tenant,
            Capability::ANALYTICS_BASIC,
            Permission::ANALYTICS_EXPORT
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date|date_format:Y-m-d|before_or_equal:end_date',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:today',
            'status' => 'nullable|string|in:pending,confirmed,processing,ready,delivered,cancelled',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'start_date.before_or_equal' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'end_date.before_or_equal' => 'La fecha de fin no puede ser futura.',
            'status.in' => 'El estado proporcionado no es válido.',
        ];
    }

    /**
     * Get validated start date with sensible default.
     */
    public function getStartDate(): string
    {
        return $this->validated()['start_date'] ?? now()->subMonth()->toDateString();
    }

    /**
     * Get validated end date with sensible default.
     */
    public function getEndDate(): string
    {
        return $this->validated()['end_date'] ?? now()->toDateString();
    }

    /**
     * Get validated status filter (nullable).
     */
    public function getStatus(): ?string
    {
        return $this->validated()['status'] ?? null;
    }

    public function tenantForRequest(): Tenant
    {
        return app(RequestTenantResolver::class)->resolve($this);
    }
}
