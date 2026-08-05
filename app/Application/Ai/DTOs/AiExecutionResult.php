<?php

declare(strict_types=1);

namespace App\Application\Ai\DTOs;

final readonly class AiExecutionResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array{input_tokens:int,output_tokens:int,cost_usd:float,charged_cost_usd:float,provider_cost_usd:float}  $usage
     */
    public function __construct(
        public string $correlationId,
        public string $provider,
        public string $model,
        public array $data,
        public array $usage,
        public int $latencyMilliseconds,
    ) {}
}
