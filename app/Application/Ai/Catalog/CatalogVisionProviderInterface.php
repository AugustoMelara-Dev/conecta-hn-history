<?php

declare(strict_types=1);

namespace App\Application\Ai\Catalog;

interface CatalogVisionProviderInterface
{
    public function analyze(CatalogVisionImage $image): CatalogVisionProviderResult;
}
