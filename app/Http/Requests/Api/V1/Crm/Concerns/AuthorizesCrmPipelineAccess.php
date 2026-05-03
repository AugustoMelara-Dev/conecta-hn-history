<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Crm\Concerns;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\RequestTenantResolver;

trait AuthorizesCrmPipelineAccess
{
    public function authorizeCrmPipeline(Permission $permission): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $tenant = app(RequestTenantResolver::class)->resolve($this);

        if (! $tenant instanceof Tenant || ! $tenant->hasFeature(TenantFeature::CRM_PIPELINES)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return app(TenantPermissionGuardInterface::class)->allows(
            $user,
            $tenant,
            Capability::CRM_PIPELINE_MANAGEMENT,
            $permission,
        );
    }

    public function tenantForRequest(): Tenant
    {
        return app(RequestTenantResolver::class)->resolve($this);
    }
}
