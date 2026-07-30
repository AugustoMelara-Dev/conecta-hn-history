<?php

declare(strict_types=1);

namespace App\Application\Ai\Contracts;

interface AiProviderPool
{
    public function resolve(string $adapterKey, string $secretReference): AiProvider;
}
