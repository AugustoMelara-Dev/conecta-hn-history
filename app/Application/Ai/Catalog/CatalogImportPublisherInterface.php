<?php

declare(strict_types=1);

namespace App\Application\Ai\Catalog;

interface CatalogImportPublisherInterface
{
    public function publish(string $tenantId, string $batchId): CatalogImportPublicationResult;
}
