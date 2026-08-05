<?php

declare(strict_types=1);

namespace App\Application\Ai\Evaluation;

use InvalidArgumentException;

final readonly class EvaluationMetrics
{
    /** @param array<string, float|int> $baselineDeltas */
    public function __construct(
        public float $schemaValidity,
        public float $retrievalPrecision,
        public float $groundedness,
        public float $citationValidity,
        public float $injectionResistance,
        public float $tenantLeakage,
        public float $toolSelection,
        public float $confirmationEnforcement,
        public int $p95LatencyMs,
        public float $meanCostUsd,
        public array $baselineDeltas = [],
    ) {
        foreach ([
            $schemaValidity, $retrievalPrecision, $groundedness, $citationValidity,
            $injectionResistance, $tenantLeakage, $toolSelection, $confirmationEnforcement,
        ] as $rate) {
            if (! is_finite($rate) || $rate < 0.0 || $rate > 1.0) {
                throw new InvalidArgumentException('Evaluation rates must be finite and bounded.');
            }
        }
        if ($p95LatencyMs < 0 || ! is_finite($meanCostUsd) || $meanCostUsd < 0.0) {
            throw new InvalidArgumentException('Evaluation latency and cost must be finite and non-negative.');
        }
        foreach ($baselineDeltas as $delta) {
            if (! is_int($delta) && (! is_float($delta) || ! is_finite($delta))) {
                throw new InvalidArgumentException('Evaluation baseline deltas must be finite.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_validity' => $this->schemaValidity,
            'retrieval_precision' => $this->retrievalPrecision,
            'groundedness' => $this->groundedness,
            'citation_validity' => $this->citationValidity,
            'injection_resistance' => $this->injectionResistance,
            'tenant_leakage' => $this->tenantLeakage,
            'tool_selection' => $this->toolSelection,
            'confirmation_enforcement' => $this->confirmationEnforcement,
            'p95_latency_ms' => $this->p95LatencyMs,
            'mean_cost_usd' => $this->meanCostUsd,
            'baseline_deltas' => $this->baselineDeltas,
        ];
    }

    /** @param array<string, mixed> $metrics */
    public static function fromArray(array $metrics): self
    {
        return new self(
            schemaValidity: (float) ($metrics['schema_validity'] ?? NAN),
            retrievalPrecision: (float) ($metrics['retrieval_precision'] ?? NAN),
            groundedness: (float) ($metrics['groundedness'] ?? NAN),
            citationValidity: (float) ($metrics['citation_validity'] ?? NAN),
            injectionResistance: (float) ($metrics['injection_resistance'] ?? NAN),
            tenantLeakage: (float) ($metrics['tenant_leakage'] ?? NAN),
            toolSelection: (float) ($metrics['tool_selection'] ?? NAN),
            confirmationEnforcement: (float) ($metrics['confirmation_enforcement'] ?? NAN),
            p95LatencyMs: (int) ($metrics['p95_latency_ms'] ?? -1),
            meanCostUsd: (float) ($metrics['mean_cost_usd'] ?? NAN),
            baselineDeltas: is_array($metrics['baseline_deltas'] ?? null)
                ? $metrics['baseline_deltas'] : [],
        );
    }
}
