<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface FileUrlResolverInterface
{
    public function url(string $path): string;
}
