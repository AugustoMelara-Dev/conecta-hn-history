<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Shared\Contracts\FileUrlResolverInterface;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * LaravelFileUrlResolver — Infrastructure adapter for FileUrlResolverInterface.
 *
 * Wraps Illuminate's Filesystem contract so the Application layer
 * never imports Illuminate types directly.
 */
final readonly class LaravelFileUrlResolver implements FileUrlResolverInterface
{
    public function __construct(
        private Filesystem $filesystem,
    ) {}

    public function url(string $path): string
    {
        return $this->filesystem->url($path);
    }
}
