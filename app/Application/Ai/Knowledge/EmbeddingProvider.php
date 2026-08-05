<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge;

interface EmbeddingProvider
{
    public function embed(string $content): EmbeddingResult;
}
