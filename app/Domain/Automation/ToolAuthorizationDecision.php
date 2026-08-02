<?php

declare(strict_types=1);

namespace App\Domain\Automation;

final readonly class ToolAuthorizationDecision
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        public bool $allowed,
        public string $code,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function allow(string $code = 'authorized', array $metadata = []): self
    {
        return new self(true, $code, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function deny(string $code, array $metadata = []): self
    {
        return new self(false, $code, $metadata);
    }
}
