<?php

declare(strict_types=1);

namespace App\Application\Ai\Catalog;

final readonly class CatalogVisionImage
{
    public function __construct(
        public string $tenantId,
        public string $itemId,
        public string $originalName,
        public string $mimeType,
        public string $contents,
    ) {}
}
