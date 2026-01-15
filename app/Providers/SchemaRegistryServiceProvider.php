<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Common\SchemaRegistry\LocalFileSchemaRegistry;
use App\Infrastructure\Common\SchemaRegistry\SchemaRegistryInterface;
use Illuminate\Support\ServiceProvider;

class SchemaRegistryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(SchemaRegistryInterface::class, function ($app) {
            // "Convention over Configuration": Schemas live in resources/schemas
            return new LocalFileSchemaRegistry(resource_path('schemas'));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
