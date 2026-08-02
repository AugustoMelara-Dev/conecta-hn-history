<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Dovra;

final readonly class DataEnvelope
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $sourceSystem,
        public array $payload,
        public ?string $tenantId = null,
        public ?string $actorId = null,
        public string $channel = 'application',
        public array $context = [],
    ) {}
}
