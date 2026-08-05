<?php

declare(strict_types=1);

namespace App\Application\Ai\Evaluation;

use App\Exceptions\Ai\AiExecutionDeniedException;

final class EvaluationRequestEvidence
{
    /** @var list<string> */
    private const KEYS = [
        'tenant_id', 'evaluation_version_id', 'evaluation_version_checksum',
        'release_policy_id', 'release_policy_checksum', 'agent_version_id',
        'candidate_checksum', 'prompt_version_id', 'prompt_checksum',
        'provider_version_id', 'provider_checksum', 'model_version_id',
        'model_checksum', 'dataset_checksum', 'evaluator_version',
        'evaluator_checksum', 'deterministic_seed', 'baseline_run_id', 'artifact_sha',
    ];

    /** @param array<string, mixed> $bindings */
    public static function checksum(array $bindings): string
    {
        $actual = array_keys($bindings);
        $expected = self::KEYS;
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new AiExecutionDeniedException('evaluation_gate');
        }

        return EvaluationChecksum::hash($bindings);
    }
}
