<?php

declare(strict_types=1);

namespace App\Application\Ai\Catalog;

use App\Domain\Catalog\Enums\CatalogImportStatus;

final readonly class CatalogImportPublicationResult
{
    public function __construct(
        public string $batchId,
        public CatalogImportStatus $status,
        public int $publishedItems,
    ) {}
}
