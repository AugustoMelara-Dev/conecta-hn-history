<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\DTOs\AiExecutionClaim;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Exceptions\Ai\AiExecutionFailedException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AiExecutionStore
{
    public function claim(AiExecutionRequest $request): AiExecutionClaim
    {
        $hash = $this->requestHash($request);
        $now = now();
        $claimToken = bin2hex(random_bytes(32));
        $inserted = DB::table('ai_executions')->insertOrIgnore([
            'correlation_id' => $request->context->correlationId,
            'tenant_id' => $request->context->tenantId,
            'billing_tenant_id' => $request->context->billingTenantId,
            'actor_id' => $request->context->actorId === null ? null : (string) $request->context->actorId,
            'audience' => $request->context->audience,
            'capability' => $request->context->capability,
            'operation' => $request->operation,
            'provider' => $request->selection->providerKey,
            'provider_version_id' => $request->selection->providerVersionId,
            'model_version_id' => $request->selection->modelVersionId,
            'agent_version_id' => $request->selection->agentVersionId,
            'prompt_version_id' => $request->selection->promptVersionId,
            'request_hash' => $hash,
            'claim_token' => $claimToken,
            'claim_version' => 1,
            'status' => 'processing',
            'processing_expires_at' => $this->leaseExpiresAt(),
            'encryption_key_version' => (string) config('ai.execution_retention.encryption_key_version', 'v1'),
            'retention_expires_at' => $now->copy()->addDays(max(1, (int) config('ai.execution_retention.days', 30))),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 1) {
            return AiExecutionClaim::active($claimToken);
        }

        $existing = DB::table('ai_executions')
            ->where('correlation_id', $request->context->correlationId)
            ->first();

        if ($existing === null) {
            throw new RuntimeException('AI execution claim could not be persisted.');
        }

        if (! hash_equals((string) $existing->request_hash, $hash)) {
            throw new AiExecutionDeniedException('idempotency_conflict');
        }

        if ((string) $existing->status === 'completed') {
            return AiExecutionClaim::replay($this->restoreResult($existing));
        }

        if ((string) $existing->status === 'processing' && $existing->reservation_id === null) {
            $reclaimed = DB::table('ai_executions')
                ->where('correlation_id', $request->context->correlationId)
                ->where('request_hash', $hash)
                ->where('status', 'processing')
                ->whereNull('reservation_id')
                ->where('processing_expires_at', '<=', now())
                ->update([
                    'claim_token' => $claimToken,
                    'claim_version' => DB::raw('claim_version + 1'),
                    'processing_expires_at' => $this->leaseExpiresAt(),
                    'updated_at' => now(),
                ]);

            if ($reclaimed === 1) {
                return AiExecutionClaim::active($claimToken);
            }
        }

        if (in_array((string) $existing->status, ['processing', 'accounting_pending'], true)) {
            throw new AiExecutionDeniedException('execution_in_progress');
        }

        throw new AiExecutionFailedException('The previous AI execution did not complete.');
    }

    public function attachReservation(string $correlationId, string $claimToken, string $reservationId): void
    {
        $updated = DB::table('ai_executions')
            ->where('correlation_id', $correlationId)
            ->where('claim_token', $claimToken)
            ->where('status', 'processing')
            ->whereNull('reservation_id')
            ->where('processing_expires_at', '>', now())
            ->update(['reservation_id' => $reservationId, 'updated_at' => now()]);

        if ($updated !== 1) {
            throw new RuntimeException('AI execution reservation could not be attached.');
        }
    }

    public function beginProviderCall(string $correlationId, string $claimToken, string $reservationId): void
    {
        $updated = DB::table('ai_executions')
            ->where('correlation_id', $correlationId)
            ->where('claim_token', $claimToken)
            ->where('status', 'processing')
            ->where('reservation_id', $reservationId)
            ->where('processing_expires_at', '>', now())
            ->update([
                'processing_expires_at' => now()->addMinutes(30),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('AI execution claim is no longer active.');
        }
    }

    public function stageResult(
        string $correlationId,
        string $claimToken,
        AiProviderResult $providerResult,
        array $validated,
        float $chargedCost,
        float $chargedCredits,
        string $pricingVersion,
        float $creditsPerProviderUsd,
        float $minimumCharge,
        int $latencyMilliseconds,
    ): void {
        $json = json_encode($validated, JSON_THROW_ON_ERROR);
        $usage = [
            'input_tokens' => $providerResult->inputTokens,
            'output_tokens' => $providerResult->outputTokens,
            'cost_usd' => $chargedCost,
            'charged_cost_usd' => $chargedCost,
            'provider_cost_usd' => $providerResult->costUsd,
        ];

        $updated = DB::table('ai_executions')
            ->where('correlation_id', $correlationId)
            ->where('claim_token', $claimToken)
            ->where('status', 'processing')
            ->update([
                'status' => 'accounting_pending',
                'result_ciphertext' => Crypt::encryptString($json),
                'result_hash' => hash('sha256', $json),
                'model' => mb_substr($providerResult->model, 0, 100),
                'usage' => json_encode($usage, JSON_THROW_ON_ERROR),
                'latency_milliseconds' => $latencyMilliseconds,
                'provider_cost' => $providerResult->costUsd,
                'charged_cost' => $chargedCost,
                'charged_credits' => $chargedCredits,
                'credit_pricing_version' => mb_substr($pricingVersion, 0, 32),
                'credits_per_provider_usd' => $creditsPerProviderUsd,
                'credit_minimum_charge' => $minimumCharge,
                'provider_completed_at' => now()->utc(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('AI execution result could not be staged.');
        }
    }

    public function stageResourceOverrun(
        string $correlationId,
        string $claimToken,
        AiProviderResult $providerResult,
        float $chargedCost,
        float $chargedCredits,
        string $pricingVersion,
        float $creditsPerProviderUsd,
        float $minimumCharge,
        string $failureCode,
        int $latencyMilliseconds,
    ): void {
        $usage = [
            'input_tokens' => $providerResult->inputTokens,
            'output_tokens' => $providerResult->outputTokens,
            'cost_usd' => $chargedCost,
            'charged_cost_usd' => $chargedCost,
            'provider_cost_usd' => $providerResult->costUsd,
        ];

        $updated = DB::table('ai_executions')
            ->where('correlation_id', $correlationId)
            ->where('claim_token', $claimToken)
            ->where('status', 'processing')
            ->update([
                'status' => 'accounting_pending',
                'failure_code' => mb_substr($failureCode, 0, 64),
                'model' => mb_substr($providerResult->model, 0, 100),
                'usage' => json_encode($usage, JSON_THROW_ON_ERROR),
                'latency_milliseconds' => $latencyMilliseconds,
                'provider_cost' => $providerResult->costUsd,
                'charged_cost' => $chargedCost,
                'charged_credits' => $chargedCredits,
                'credit_pricing_version' => mb_substr($pricingVersion, 0, 32),
                'credits_per_provider_usd' => $creditsPerProviderUsd,
                'credit_minimum_charge' => $minimumCharge,
                'provider_completed_at' => now()->utc(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('AI provider resource overrun could not be staged.');
        }
    }

    public function completeStaged(string $correlationId, string $claimToken): void
    {
        $updated = DB::table('ai_executions')
            ->where('correlation_id', $correlationId)
            ->where('claim_token', $claimToken)
            ->where('status', 'accounting_pending')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('AI execution could not be completed.');
        }
    }

    public function fail(string $correlationId, string $claimToken, string $failureCode): void
    {
        DB::table('ai_executions')
            ->where('correlation_id', $correlationId)
            ->where('claim_token', $claimToken)
            ->whereIn('status', ['processing', 'accounting_pending'])
            ->update([
                'status' => 'failed',
                'failure_code' => mb_substr($failureCode, 0, 64),
                'failed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function reconcileCompleteStaged(string $correlationId): void
    {
        $updated = DB::table('ai_executions')
            ->where('correlation_id', $correlationId)
            ->where('status', 'accounting_pending')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('AI execution could not be reconciled as completed.');
        }
    }

    public function reconcileFail(string $correlationId, string $failureCode): void
    {
        DB::table('ai_executions')
            ->where('correlation_id', $correlationId)
            ->whereIn('status', ['processing', 'accounting_pending'])
            ->update([
                'status' => 'failed',
                'failure_code' => mb_substr($failureCode, 0, 64),
                'failed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /** @return object{correlation_id:string,provider:string,model:string,result_ciphertext:?string,result_hash:?string,usage:string,latency_milliseconds:int} */
    private function completedRow(object $row): object
    {
        return $row;
    }

    private function restoreResult(object $row): AiExecutionResult
    {
        $completed = $this->completedRow($row);
        if ($completed->result_ciphertext === null || $completed->result_hash === null) {
            throw new AiExecutionFailedException('Stored AI execution payload has expired.');
        }

        $json = Crypt::decryptString($completed->result_ciphertext);

        if (! hash_equals($completed->result_hash, hash('sha256', $json))) {
            throw new AiExecutionFailedException('Stored AI execution integrity check failed.');
        }

        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        $usage = json_decode($completed->usage, true, 8, JSON_THROW_ON_ERROR);

        if (! is_array($data) || ! is_array($usage)) {
            throw new AiExecutionFailedException('Stored AI execution is invalid.');
        }

        /** @var array<string, mixed> $data */
        $normalizedUsage = [
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'cost_usd' => (float) ($usage['cost_usd'] ?? 0),
            'charged_cost_usd' => (float) ($usage['charged_cost_usd'] ?? 0),
            'provider_cost_usd' => (float) ($usage['provider_cost_usd'] ?? 0),
        ];

        return new AiExecutionResult(
            correlationId: $completed->correlation_id,
            provider: $completed->provider,
            model: $completed->model,
            data: $data,
            usage: $normalizedUsage,
            latencyMilliseconds: $completed->latency_milliseconds,
        );
    }

    private function leaseExpiresAt(): Carbon
    {
        return now()->addMinutes(20);
    }

    private function requestHash(AiExecutionRequest $request): string
    {
        return hash('sha256', serialize([
            'audience' => $request->context->audience,
            'capability' => $request->context->capability,
            'provider' => $request->selection->providerKey,
            'provider_version_id' => $request->selection->providerVersionId,
            'model_version_id' => $request->selection->modelVersionId,
            'agent_version_id' => $request->selection->agentVersionId,
            'prompt_version_id' => $request->selection->promptVersionId,
            'release_decision_id' => $request->selection->releaseDecisionId,
            'adapter_key' => $request->selection->adapterKey,
            'model_key' => $request->selection->modelKey,
            'tenant_id' => $request->context->tenantId,
            'billing_tenant_id' => $request->context->billingTenantId,
            'actor_id' => $request->context->actorId,
            'operation' => $request->operation,
            'system_prompt_hash' => hash('sha256', $request->systemPrompt),
            'user_input_hash' => hash('sha256', $request->userInput),
            'output_schema' => $request->outputSchema,
            'requested_tools' => $request->requestedTools,
            'max_output_tokens' => $request->maxOutputTokens,
        ]));
    }
}
