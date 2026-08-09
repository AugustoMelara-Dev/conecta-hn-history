<?php

declare(strict_types=1);

namespace App\Providers\Domain;

use App\Application\Geography\Contracts\TenantCoverageWriter;
use App\Application\Knowledge\Contracts\CommercialKnowledgeHealthReader;
use App\Infrastructure\Geography\EloquentTenantCoverageWriter;
use App\Infrastructure\Knowledge\EloquentCommercialKnowledgeHealthReader;
use Illuminate\Support\ServiceProvider;

final class GeographyKnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantCoverageWriter::class, EloquentTenantCoverageWriter::class);
        $this->app->bind(CommercialKnowledgeHealthReader::class, EloquentCommercialKnowledgeHealthReader::class);
    }
}
