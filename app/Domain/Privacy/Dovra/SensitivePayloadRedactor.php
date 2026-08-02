<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Dovra;

interface SensitivePayloadRedactor
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function scrub(array $payload): array;
}
