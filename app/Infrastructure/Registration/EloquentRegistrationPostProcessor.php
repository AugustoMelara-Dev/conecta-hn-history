<?php

declare(strict_types=1);

namespace App\Infrastructure\Registration;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class EloquentRegistrationPostProcessor
{
    public function resolveUser(mixed $result, string $email): ?Authenticatable
    {
        if (is_array($result) && isset($result['user']) && $result['user'] instanceof Authenticatable) {
            return $result['user'];
        }

        return User::query()->where('email', $email)->first();
    }

    public function ensureUserTenantForSlug(Authenticatable $user, string $slug): ?string
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant instanceof Tenant || ! method_exists($user, 'forceFill')) {
            return null;
        }

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return "/app/{$tenant->slug}";
    }
}
