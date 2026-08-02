<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Dovra;

final readonly class PrivacyRequestResult
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public string $requestId,
        public string $action,
        public string $status,
        public array $summary = [],
    ) {}
}
