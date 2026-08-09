<?php

declare(strict_types=1);

/**
 * MarketingServiceProvider - Domain Wiring
 *
 * PURPOSE: Register bindings and events for Marketing campaigns and emails.
 * MIGRATED: From App\Domain\Marketing to App\Providers\Domain (2026-01-07)
 */

namespace App\Providers\Domain;

use App\Application\Channels\Contracts\ChannelAdapterResolverInterface;
use App\Infrastructure\Channels\ConfigChannelAdapterResolver;
use App\Infrastructure\Console\Commands\DispatchDueCampaignsCommand;
use Illuminate\Support\ServiceProvider;

final class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChannelAdapterResolverInterface::class, ConfigChannelAdapterResolver::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([DispatchDueCampaignsCommand::class]);
        }
    }
}
