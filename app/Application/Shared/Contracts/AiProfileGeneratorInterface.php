<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface AiProfileGeneratorInterface
{
    /**
     * Generate an AI-enhanced profile based on raw tenant inputs.
     *
     * @return array{description: string, about: string, tags: array<string>, seo_keywords: array<string>, status: string}
     */
    public function generate(string $rawName, string $rawDescription, string $rawCategory): array;
}
