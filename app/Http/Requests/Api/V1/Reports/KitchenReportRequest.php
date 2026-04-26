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
            // Kitchen prep lists are real-time, no date filtering
            // Future: could add shift, category, priority filters
        ];
    }

    public function tenantForRequest(): Tenant
    {
        return app(RequestTenantResolver::class)->resolve($this);
    }
}
