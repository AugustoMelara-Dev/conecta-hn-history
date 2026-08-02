<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Dovra;

final readonly class DataDecision
{
    /**
     * @param  list<string>  $reasons
     * @param  array<string, mixed>  $redactedPayload
     * @param  list<string>  $integrationSeams
     */
    public function __construct(
        public bool $allowed,
        public string $classification,
        public string $retentionCategory,
        public array $reasons,
        public array $redactedPayload,
        public array $integrationSeams = [],
    ) {}
}
