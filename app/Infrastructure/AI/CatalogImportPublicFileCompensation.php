<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use Illuminate\Contracts\Filesystem\Filesystem;

/** Ensures a losing publisher attempt cannot delete another attempt's file. */
final class CatalogImportPublicFileCompensation
{
    public function deleteIfOwned(Filesystem $disk, ?string $path, bool $ownedPromotion): void
    {
        if ($ownedPromotion && $path !== null) {
            $disk->delete($path);
        }
    }
}
