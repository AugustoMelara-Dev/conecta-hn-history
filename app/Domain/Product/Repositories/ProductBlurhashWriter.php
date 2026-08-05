<?php

declare(strict_types=1);

namespace App\Domain\Product\Repositories;

interface ProductBlurhashWriter
{
    public function updateBlurhash(string $tenantId, int $productId, string $blurhash): bool;
}
