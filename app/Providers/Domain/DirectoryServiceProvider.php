<?php

declare(strict_types=1);

namespace App\Providers\Domain;

use App\Application\Directory\Contracts\PublicTenantEngagementGatewayInterface;
use App\Infrastructure\Directory\EloquentPublicTenantEngagementGateway;
use Illuminate\Support\ServiceProvider;

final class DirectoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PublicTenantEngagementGatewayInterface::class,
            EloquentPublicTenantEngagementGateway::class,
        );
    }
}
