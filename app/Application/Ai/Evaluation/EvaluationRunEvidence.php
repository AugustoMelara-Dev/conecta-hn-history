<?php

declare(strict_types=1);

namespace App\Application\Ai\Evaluation;

use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EvaluationRunEvidence
{
    /** @return array{metrics:EvaluationMetrics,blocking_codes:list<string>,result_count:int,all_passed:bool} */
    public function assert(object $run, bool $lockForUpdate = false): array
    {
        if (! in_array((string) $run->status, ['passed', 'failed'], true)
            || ! is_numeric($run->blocking_failures)
            || ! $this->checksum($run->result_checksum)
            || ! $this->ordered($run->started_at, $run->completed_at)
            || ! $this->ordered($run->completed_at, now())) {
            $this->deny();
        }
        $metricsArray = $this->object($run->aggregate_metrics);
        $metrics = EvaluationMetrics::fromArray($metricsArray);
        $blockingCodes = $this->list($run->blocking_codes);
        if (count($blockingCodes) !== (int) $run->blocking_failures
            || count(array_unique($blockingCodes)) !== count($blockingCodes)) {
            $this->deny();
        }

        $caseQuery = DB::table('ai_evaluation_cases')
            ->where('tenant_id', $run->tenant_id)
            ->where('evaluation_version_id', $run->evaluation_version_id);
        $resultQuery = DB::table('ai_evaluation_results')
            ->where('tenant_id', $run->tenant_id)
            ->where('evaluation_run_id', $run->id);
        if ($lockForUpdate) {
            $caseQuery->lockForUpdate();
            $resultQuery->lockForUpdate();
        }
        $cases = $caseQuery->get()->keyBy(static fn (object $case): string => (string) $case->id);
        $results = $resultQuery->get();
        if ($cases->isEmpty() || $results->count() !== $cases->count()) {
            $this->deny();
        }
        $seen = [];
        $checksums = [];
        $allPassed = true;
        foreach ($results as $result) {
            $caseId = (string) $result->evaluation_case_id;
            $case = $cases->get($caseId);
            if (! is_object($case) || isset($seen[$caseId])
                || ! in_array((string) $result->status, ['passed', 'failed'], true)
                || ! $this->checksum($result->input_checksum)
                || ! hash_equals((string) $case->input_hash, (string) $result->input_checksum)
                || ! $this->checksum($result->output_hash)
                || ! $this->checksum($result->result_checksum)
                || ! is_numeric($result->latency_ms) || (int) $result->latency_ms < 0
                || ! is_numeric($result->cost_usd) || ! is_finite((float) $result->cost_usd) || (float) $result->cost_usd < 0
                || ! $this->ordered($run->started_at, $result->completed_at)
                || ! $this->ordered($result->completed_at, $run->completed_at)
                || ($result->diagnostic_code !== null
                    && (! is_string($result->diagnostic_code)
                        || preg_match('/^[a-z][a-z0-9_]{2,63}$/', $result->diagnostic_code) !== 1))
                || $result->failure_detail !== $result->diagnostic_code) {
                $this->deny();
            }
            $safeMetrics = $this->caseMetrics($this->object($result->metrics));
            $expected = EvaluationChecksum::hash([
                'evaluation_case_id' => $caseId,
                'status' => (string) $result->status,
                'input_checksum' => (string) $result->input_checksum,
                'output_hash' => (string) $result->output_hash,
                'metrics' => $safeMetrics,
                'latency_ms' => (int) $result->latency_ms,
                'cost_usd' => number_format((float) $result->cost_usd, 6, '.', ''),
                'diagnostic_code' => $result->diagnostic_code,
            ]);
            if (! hash_equals((string) $result->result_checksum, $expected)) {
                $this->deny();
            }
            $seen[$caseId] = true;
            $checksums[$caseId] = $expected;
            $allPassed = $allPassed && hash_equals('passed', (string) $result->status);
        }
        ksort($checksums);
        $runChecksum = EvaluationChecksum::hash([
            'case_result_checksums' => array_values($checksums),
            'metrics' => $metrics->toArray(),
            'blocking_codes' => $blockingCodes,
        ]);
        if (! hash_equals((string) $run->result_checksum, $runChecksum)) {
            $this->deny();
        }

        return [
            'metrics' => $metrics,
            'blocking_codes' => $blockingCodes,
            'result_count' => $results->count(),
            'all_passed' => $allPassed,
        ];
    }

    /** @return array<string, mixed> */
    private function object(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $this->deny();
            }
        }
        if (! is_array($value) || array_is_list($value)) {
            $this->deny();
        }

        return $value;
    }

    /** @param array<string, mixed> $metrics @return array<string, bool|float> */
    private function caseMetrics(array $metrics): array
    {
        $keys = [
            'schema_valid', 'retrieval_precision', 'grounded', 'citations_valid',
            'injection_resisted', 'tenant_safe', 'tool_selected', 'confirmation_enforced',
        ];
        if (array_diff($keys, array_keys($metrics)) !== [] || array_diff(array_keys($metrics), $keys) !== []) {
            $this->deny();
        }
        foreach (array_diff($keys, ['retrieval_precision']) as $key) {
            if (! is_bool($metrics[$key])) {
                $this->deny();
            }
        }
        if (! is_numeric($metrics['retrieval_precision'])
            || ! is_finite((float) $metrics['retrieval_precision'])
            || (float) $metrics['retrieval_precision'] < 0
            || (float) $metrics['retrieval_precision'] > 1) {
            $this->deny();
        }
        $metrics['retrieval_precision'] = (float) $metrics['retrieval_precision'];

        return $metrics;
    }

    /** @return list<string> */
    private function list(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $this->deny();
            }
        }
        if (! is_array($value) || ! array_is_list($value)
            || array_filter($value, static fn (mixed $item): bool => is_string($item) && preg_match('/^[a-z][a-z0-9_]{2,63}$/', $item) === 1) !== $value) {
            $this->deny();
        }

        return $value;
    }

    private function checksum(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    private function ordered(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }
        try {
            return Carbon::parse((string) $left)->lessThanOrEqualTo(Carbon::parse((string) $right));
        } catch (\Throwable) {
            return false;
        }
    }

    private function deny(): never
    {
        throw new AiExecutionDeniedException('evaluation_gate');
    }
}
