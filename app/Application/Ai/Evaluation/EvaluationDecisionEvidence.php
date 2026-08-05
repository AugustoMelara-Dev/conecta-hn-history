<?php

declare(strict_types=1);

namespace App\Application\Ai\Evaluation;

final class EvaluationDecisionEvidence
{
    public static function checksum(object $run): string
    {
        return EvaluationChecksum::hash([
            'evaluation_run_id' => (string) $run->id,
            'request_checksum' => (string) $run->request_checksum,
            'release_policy_id' => (string) $run->release_policy_id,
            'release_policy_checksum' => (string) $run->release_policy_checksum,
            'evaluation_version_id' => (string) $run->evaluation_version_id,
            'evaluation_version_checksum' => (string) $run->evaluation_version_checksum,
            'agent_version_id' => (string) $run->agent_version_id,
            'candidate_checksum' => (string) $run->candidate_checksum,
            'prompt_version_id' => (string) $run->prompt_version_id,
            'prompt_checksum' => (string) $run->prompt_checksum,
            'provider_version_id' => (string) $run->provider_version_id,
            'provider_checksum' => (string) $run->provider_checksum,
            'model_version_id' => (string) $run->model_version_id,
            'model_checksum' => (string) $run->model_checksum,
            'dataset_checksum' => (string) $run->dataset_checksum,
            'evaluator_version' => (string) $run->evaluator_version,
            'evaluator_checksum' => (string) $run->evaluator_checksum,
            'artifact_sha' => (string) $run->artifact_sha,
            'run_result_checksum' => (string) $run->result_checksum,
        ]);
    }
}
