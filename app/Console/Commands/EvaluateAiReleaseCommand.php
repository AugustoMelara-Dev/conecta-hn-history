<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Ai\Evaluation\AiEvaluationRunner;
use App\Application\Ai\Evaluation\AiEvaluationSelectionResolver;
use App\Exceptions\Ai\AiExecutionDeniedException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class EvaluateAiReleaseCommand extends Command
{
    protected $signature = 'dovra:ai-evaluate
        {evaluation-version : UUID of the immutable evaluation version}
        {--agent-version= : Exact candidate agent version UUID}
        {--provider-version= : Exact provider version UUID}
        {--model-version= : Exact model version UUID}
        {--artifact-sha= : Exact tested source artifact}
        {--json : Emit machine-readable result}';

    protected $description = 'Run the deterministic, version-bound AI release evaluation gate.';

    public function __construct(
        private readonly AiEvaluationSelectionResolver $resolver,
        private readonly AiEvaluationRunner $runner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $evaluationVersionId = (string) $this->argument('evaluation-version');
            $agentVersionId = (string) $this->option('agent-version');
            $providerVersionId = (string) $this->option('provider-version');
            $modelVersionId = (string) $this->option('model-version');
            $artifactSha = (string) $this->option('artifact-sha');
            foreach ([$evaluationVersionId, $agentVersionId, $providerVersionId, $modelVersionId] as $id) {
                if (! Str::isUuid($id)) {
                    throw new AiExecutionDeniedException('evaluation_gate');
                }
            }
            $platformTenantId = config('ai.public.billing_tenant_id');
            $owner = DB::table('ai_evaluation_versions')->where('id', $evaluationVersionId)->value('tenant_id');
            if (! is_string($platformTenantId) || ! Str::isUuid($platformTenantId)
                || ! is_string($owner) || ! hash_equals($platformTenantId, $owner)) {
                throw new AiExecutionDeniedException('evaluation_gate');
            }
            $selection = $this->resolver->resolve(
                $evaluationVersionId, $agentVersionId, $providerVersionId, $modelVersionId,
            );
            $decision = $this->runner->run($evaluationVersionId, $selection, $artifactSha);
            $payload = [
                'run_id' => $decision->runId,
                'status' => $decision->status,
                'metrics' => $decision->metrics->toArray(),
                'blockers' => $decision->blockingFailures,
            ];
            $this->emit($payload);

            return $decision->status === 'passed' && $decision->blockingFailures === []
                ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->emit([
                'run_id' => null,
                'status' => 'invalid',
                'metrics' => null,
                'blockers' => ['evaluation_gate'],
            ]);

            return self::FAILURE;
        }
    }

    /** @param array<string, mixed> $payload */
    private function emit(array $payload): void
    {
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return;
        }
        $this->line(sprintf(
            'AI evaluation %s (%d blocker%s).',
            (string) $payload['status'],
            count(is_array($payload['blockers']) ? $payload['blockers'] : []),
            count(is_array($payload['blockers']) ? $payload['blockers'] : []) === 1 ? '' : 's',
        ));
    }
}
