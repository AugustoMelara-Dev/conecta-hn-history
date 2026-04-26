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
            // Inventory reports are point-in-time, no date filtering needed
            // Future: could add category_id, low_stock_only, etc.
        ];
    }

    public function tenantForRequest(): Tenant
    {
        return app(RequestTenantResolver::class)->resolve($this);
    }
}
