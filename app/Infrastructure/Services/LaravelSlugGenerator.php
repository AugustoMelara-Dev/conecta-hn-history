<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Shared\Contracts\SlugGeneratorInterface;
use Illuminate\Support\Str;

/**
 * LaravelSlugGenerator - Infrastructure Adapter for Slug Generation
 *
 * Implements the Application Layer's SlugGeneratorInterface using
 * Laravel's Str::slug() utility.
 */
final readonly class LaravelSlugGenerator implements SlugGeneratorInterface
{
    public function generate(string $text): string
    {
        return Str::slug($text);
    }
}
