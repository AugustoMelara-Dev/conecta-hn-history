<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

interface MetadataSanitizerInterface
{
    /**
     * Recursively sanitize a metadata array.
     *
     * @param  array<mixed>  $metadata  Raw metadata payload
     * @return array<mixed>             Sanitized payload
     */
    public function clean(array $metadata): array;
}
