<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
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
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $tenant = $this->resolveTenant($user);

        if (! $tenant instanceof Tenant) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return app(TenantPermissionGuardInterface::class)->allows(
            $user,
            $tenant,
            Capability::AI_CONTENT,
            Permission::AI_USE
        );
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

    private function resolveTenant(User $user): ?Tenant
    {
        if (app()->bound(TenantContext::class)) {
            try {
                $tenantId = app(TenantContext::class)->getTenantUuid();
            } catch (\Throwable) {
                $tenantId = '';
            }

            if ($tenantId !== '') {
                $tenant = Tenant::query()->find($tenantId);

                if ($tenant instanceof Tenant) {
                    return $tenant;
                }
            }
        }

        return $user->tenant instanceof Tenant ? $user->tenant : null;
    }
}
