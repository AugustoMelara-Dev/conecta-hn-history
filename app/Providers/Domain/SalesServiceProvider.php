<?php

declare(strict_types=1);

namespace App\Providers\Domain;

use App\Application\Sales\Contracts\PublicOrderCreator;
use App\Infrastructure\Sales\EloquentPublicOrderCreator;
use Illuminate\Support\ServiceProvider;

final class SalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PublicOrderCreator::class, EloquentPublicOrderCreator::class);
    }
}
