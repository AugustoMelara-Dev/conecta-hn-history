<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * SlugGeneratorInterface - URL-Safe String Transformation Contract
 *
 * Abstracts slug generation so the Application Layer remains decoupled
 * from any specific string utility (e.g., Laravel's Str::slug).
 */
interface SlugGeneratorInterface
{
    /**
     * Generate a URL-safe slug from the given text.
     */
    public function generate(string $text): string;
}
