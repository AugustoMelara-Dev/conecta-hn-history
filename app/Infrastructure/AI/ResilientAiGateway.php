<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\Contracts\StructuredOutputValidator;
use App\Application\Ai\DTOs\AiExecutionRequest;
use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\DTOs\AiProviderRequest;
use App\Application\Ai\DTOs\AiProviderResult;
use App\Application\Ai\Policies\AiCapabilityPolicy;
use App\Domain\Billing\Services\AiCreditPricingService;
use App\Exceptions\Ai\AiCircuitOpenException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Exceptions\Ai\AiExecutionFailedException;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderResourceLimitException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class ResilientAiGateway implements AiGateway
{
    public function __construct(
        private readonly AiProviderPool $providers,
        private readonly StructuredOutputValidator $validator,
        private readonly AiCapabilityPolicy $policy,
        private readonly BudgetEnforcer $budget,
        private readonly AiExecutionStore $executions,
        ?AiCreditPricingService $creditPricing = null,
    ) {
        $this->creditPricing = $creditPricing ?? new AiCreditPricingService;
    }

    private readonly AiCreditPricingService $creditPricing;

    public function execute(AiExecutionRequest $request): AiExecutionResult
    {
        $context = $request->context;
        $this->policy->assertExecutionAllowed($context);
        $this->policy->assertRequestAllowed($request);
        $provider = $this->providers->resolve($request->selection->adapterKey);

        if (! hash_equals($request->selection->providerKey, $provider->name())) {
            throw new AiExecutionDeniedException('provider_binding');
        }

        $this->assertPromptBounds($request);
        $maxCost = $this->registryPreflightCost($request);
        $maxCredits = $this->creditPricing->fromProviderUsd($maxCost);

        // This cheap, serialized gate runs before any durable write so rejected
        // traffic cannot fill ai_executions with arbitrary correlation IDs.
        $this->assertRateLimit($request);

        $claim = $this->executions->claim($request);
        if ($claim->replay !== null) {
            return $claim->replay;
        }

        $claimToken = $claim->claimToken;
        if ($claimToken === null) {
            throw new AiExecutionFailedException('AI execution claim is invalid.');
        }

        try {
            $this->assertCircuitClosed($request->selection->providerKey, $context->capability);
        } catch (Throwable $exception) {
            $this->executions->fail($context->correlationId, $claimToken, 'circuit_open');
            throw $exception;
        }

        try {
            $reservationId = DB::transaction(function () use ($context, $maxCredits, $request, $claimToken): string {
                $reservationId = $this->budget->reserve(
                    $context->billingTenantId,
                    $maxCredits,
                    $request->operation,
                );
                $this->executions->attachReservation($context->correlationId, $claimToken, $reservationId);

                return $reservationId;
            }, 3);
            $pricingSnapshot = $this->budget->pricingSnapshot($context->billingTenantId, $reservationId);
        } catch (Throwable $exception) {
            $this->executions->fail($context->correlationId, $claimToken, 'budget_reservation_failed');
            throw $exception;
        }

        $started = hrtime(true);

        try {
            $this->executions->beginProviderCall($context->correlationId, $claimToken, $reservationId);
            $providerResult = $provider->generate(new AiProviderRequest(
                correlationId: $context->correlationId,
                capability: $context->capability,
                modelKey: $request->selection->modelKey,
                systemPrompt: $request->systemPrompt,
                userInput: $request->userInput,
                timeoutMilliseconds: (int) config('ai.limits.timeout_milliseconds', 15_000),
                maxOutputTokens: $request->maxOutputTokens,
                outputSchema: $request->outputSchema,
            ));
            $this->recordProviderSuccess($request->selection->providerKey, $context->capability);
        } catch (AiProviderUnavailableException $exception) {
            $this->recordProviderFailure($request->selection->providerKey, $context->capability);
            $this->refundAndFail($request, $maxCredits, $reservationId, $claimToken, 'provider_unavailable');
            $this->logFailure($request, $exception);

            // Never leak or chain the provider's potentially sensitive message.
            throw new AiProviderUnavailableException('AI provider is temporarily unavailable.');
        } catch (Throwable $exception) {
            $this->refundAndFail($request, $maxCredits, $reservationId, $claimToken, 'provider_contract_failure');
            $this->logFailure($request, $exception);

            throw new AiProviderUnavailableException('AI provider is temporarily unavailable.');
        }

        $latency = (int) round((hrtime(true) - $started) / 1_000_000);

        try {
            $this->assertProviderResourcePolicy($request, $providerResult, $maxCost);
        } catch (AiProviderResourceLimitException $exception) {
            $this->handleProviderResourceOverrun(
                $request,
                $providerResult,
                $maxCost,
                $maxCredits,
                $reservationId,
                $pricingSnapshot,
                $claimToken,
                $latency,
                $exception->reason,
            );

            throw new AiExecutionFailedException('AI provider exceeded authorized resource limits.');
        }

        try {
            $this->assertProviderOutputBounds($providerResult);
            $validated = $this->validator->validate($providerResult->data, $this->schemaRules($request));
        } catch (AiMalformedResponseException $exception) {
            $this->refundAndFail($request, $maxCredits, $reservationId, $claimToken, 'malformed_response');
            $this->logFailure($request, $exception);

            throw new AiMalformedResponseException('AI provider returned output outside the required schema.');
        } catch (Throwable $exception) {
            $this->refundAndFail($request, $maxCredits, $reservationId, $claimToken, 'validation_failure');
            $this->logFailure($request, $exception);

            throw new AiExecutionFailedException('AI execution could not be validated.');
        }

        $chargedCost = $providerResult->costUsd;
        $chargedCredits = $this->creditPricing->fromProviderUsdAtSnapshot(
            $chargedCost,
            $pricingSnapshot['credits_per_provider_usd'],
            $pricingSnapshot['minimum_charge'],
        );

        try {
            $this->executions->stageResult(
                $context->correlationId,
                $claimToken,
                $providerResult,
                $validated,
                $chargedCost,
                $chargedCredits,
                $pricingSnapshot['version'],
                $pricingSnapshot['credits_per_provider_usd'],
                $pricingSnapshot['minimum_charge'],
                $latency,
            );

            DB::transaction(function () use (
                $context,
                $maxCredits,
                $chargedCost,
                $chargedCredits,
                $reservationId,
                $providerResult,
                $claimToken,
                $request,
            ): void {
                $this->budget->settle(
                    $context->billingTenantId,
                    $maxCredits,
                    $chargedCredits,
                    $reservationId,
                    [
                        'provider' => $request->selection->providerKey,
                        'model' => $providerResult->model,
                        'input_tokens' => $providerResult->inputTokens,
                        'output_tokens' => $providerResult->outputTokens,
                        'correlation_id' => $context->correlationId,
                        'provider_cost_usd' => $providerResult->costUsd,
                        'charged_cost_usd' => $chargedCost,
                    ],
                );
                $this->executions->completeStaged($context->correlationId, $claimToken);
            }, 3);
        } catch (Throwable $exception) {
            // The staged provider result leaves enough durable evidence for the
            // reconciler to settle this exact reservation without a refund race.
            $this->logFailure($request, $exception, 'AI accounting finalization failed.');

            throw new AiExecutionFailedException('AI accounting is pending reconciliation.');
        }

        return new AiExecutionResult(
            correlationId: $context->correlationId,
            provider: $request->selection->providerKey,
            model: $providerResult->model,
            data: $validated,
            usage: [
                'input_tokens' => $providerResult->inputTokens,
                'output_tokens' => $providerResult->outputTokens,
                'cost_usd' => $chargedCost,
                'charged_cost_usd' => $chargedCost,
                'provider_cost_usd' => $providerResult->costUsd,
            ],
            latencyMilliseconds: $latency,
        );
    }

    /** @return array<string, mixed> */
    private function schemaRules(AiExecutionRequest $request): array
    {
        $schemas = config('ai.schemas', []);
        $schema = is_array($schemas) ? ($schemas[$request->outputSchema] ?? null) : null;
        $rules = is_array($schema) ? ($schema['rules'] ?? null) : null;

        if (! is_array($rules) || $rules === []) {
            throw new AiExecutionDeniedException('output_schema');
        }

        return $rules;
    }

    private function assertPromptBounds(AiExecutionRequest $request): void
    {
        $maxInput = max(1, (int) config('ai.limits.max_user_input_characters', 2000));
        $maxSystem = max(1, (int) config('ai.limits.max_system_prompt_characters', 12000));

        if (
            mb_strlen($request->userInput) > $maxInput
            || mb_strlen($request->systemPrompt) > $maxSystem
        ) {
            throw new AiExecutionDeniedException('prompt_bounds');
        }
    }

    private function registryPreflightCost(AiExecutionRequest $request): float
    {
        $model = DB::table('ai_model_versions')
            ->where('id', $request->selection->modelVersionId)
            ->where('provider_version_id', $request->selection->providerVersionId)
            ->first([
                'context_window',
                'input_cost_minor',
                'output_cost_minor',
                'currency',
            ]);
        if (
            $model === null
            || (string) $model->currency !== 'USD'
            || ! is_numeric($model->context_window)
            || ! is_numeric($model->input_cost_minor)
            || ! is_numeric($model->output_cost_minor)
            || (int) $model->context_window < $request->maxOutputTokens
            || (int) $model->input_cost_minor < 0
            || (int) $model->output_cost_minor < 0
        ) {
            throw new AiExecutionDeniedException('registry_pricing');
        }

        $maximumInputTokens = (int) $model->context_window - $request->maxOutputTokens;
        $maximumCost = (
            ($maximumInputTokens * (int) $model->input_cost_minor)
            + ($request->maxOutputTokens * (int) $model->output_cost_minor)
        ) / 100 / 1_000_000;
        if (! is_finite($maximumCost) || $maximumCost <= 0) {
            throw new AiExecutionDeniedException('registry_pricing');
        }

        return $maximumCost;
    }

    private function assertProviderResourcePolicy(
        AiExecutionRequest $request,
        AiProviderResult $result,
        float $maxCost,
    ): void {
        if (! hash_equals($request->selection->modelKey, $result->model)) {
            throw new AiProviderResourceLimitException('model_not_authorized');
        }

        if ($result->outputTokens > $request->maxOutputTokens) {
            throw new AiProviderResourceLimitException('output_token_overrun');
        }

        if ($result->costUsd > $maxCost + 0.000001) {
            throw new AiProviderResourceLimitException('cost_overrun');
        }
    }

    private function assertProviderOutputBounds(AiProviderResult $result): void
    {
        if ($result->finishReason !== 'stop') {
            throw new AiMalformedResponseException('AI provider returned an incomplete response.');
        }

        $json = json_encode($result->data, JSON_THROW_ON_ERROR);
        $maxBytes = max(1024, (int) config('ai.limits.max_result_bytes', 65_536));
        $maxDepth = max(2, (int) config('ai.limits.max_result_depth', 8));

        if (strlen($json) > $maxBytes || $this->arrayDepth($result->data) > $maxDepth) {
            throw new AiMalformedResponseException('AI provider response exceeded safety bounds.');
        }
    }

    /** @param array<mixed> $value */
    private function arrayDepth(array $value): int
    {
        $depth = 1;
        foreach ($value as $item) {
            if (is_array($item)) {
                $depth = max($depth, 1 + $this->arrayDepth($item));
            }
        }

        return $depth;
    }

    private function assertRateLimit(AiExecutionRequest $request): void
    {
        $limit = (int) config("ai.capabilities.{$request->context->capability}.requests_per_minute");
        $key = 'ai:rate:'.hash('sha256', implode('|', [
            $request->selection->providerKey,
            $request->context->capability,
            $request->context->rateLimitSubject,
        ]));
        $lock = Cache::lock($key.':acquire', 5);

        try {
            if (! $lock->get()) {
                throw new AiExecutionDeniedException('rate_limit');
            }

            if (RateLimiter::tooManyAttempts($key, $limit)) {
                throw new AiExecutionDeniedException('rate_limit');
            }

            RateLimiter::hit($key, 60);
        } catch (AiExecutionDeniedException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('AI rate limiter acquisition failed.', [
                'provider' => $request->selection->providerKey,
                'capability' => $request->context->capability,
                'exception' => $exception::class,
            ]);
            throw new AiExecutionDeniedException('rate_limiter_unavailable');
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
                // The lock has a short TTL; a failed release must not expose data.
            }
        }
    }

    private function assertCircuitClosed(string $provider, string $capability): void
    {
        try {
            $openUntil = (int) Cache::get($this->circuitOpenKey($provider, $capability), 0);
            if ($openUntil > now()->timestamp) {
                throw new AiCircuitOpenException;
            }

            $threshold = max(1, (int) config('ai.circuit.failure_threshold', 3));
            $failures = (int) Cache::get($this->circuitFailureKey($provider, $capability), 0);
            if ($failures >= $threshold) {
                $lockAcquired = Cache::add(
                    $this->circuitHalfOpenKey($provider, $capability),
                    1,
                    max(1, (int) config('ai.circuit.cooldown_seconds', 60)),
                );

                if (! $lockAcquired) {
                    throw new AiCircuitOpenException;
                }
            }
        } catch (AiCircuitOpenException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('AI circuit state read failed.', [
                'provider' => $provider,
                'capability' => $capability,
                'exception' => $exception::class,
            ]);
            throw new AiCircuitOpenException;
        }
    }

    private function recordProviderFailure(string $provider, string $capability): void
    {
        try {
            $failureKey = $this->circuitFailureKey($provider, $capability);
            $cooldown = max(1, (int) config('ai.circuit.cooldown_seconds', 60));
            Cache::add($failureKey, 0, $cooldown * 4);
            $failures = (int) Cache::increment($failureKey);
            $threshold = max(1, (int) config('ai.circuit.failure_threshold', 3));

            if ($failures >= $threshold) {
                Cache::put(
                    $this->circuitOpenKey($provider, $capability),
                    now()->addSeconds($cooldown)->timestamp,
                    $cooldown,
                );
                Cache::forget($this->circuitHalfOpenKey($provider, $capability));
            }
        } catch (Throwable $exception) {
            Log::error('AI circuit state write failed.', [
                'provider' => $provider,
                'capability' => $capability,
                'exception' => $exception::class,
            ]);
        }
    }

    private function recordProviderSuccess(string $provider, string $capability): void
    {
        try {
            Cache::forget($this->circuitFailureKey($provider, $capability));
            Cache::forget($this->circuitOpenKey($provider, $capability));
            Cache::forget($this->circuitHalfOpenKey($provider, $capability));
        } catch (Throwable $exception) {
            Log::error('AI circuit reset failed.', [
                'provider' => $provider,
                'capability' => $capability,
                'exception' => $exception::class,
            ]);
        }
    }

    private function circuitFailureKey(string $provider, string $capability): string
    {
        return 'ai:circuit:failures:'.hash('sha256', "{$provider}|{$capability}");
    }

    private function circuitOpenKey(string $provider, string $capability): string
    {
        return 'ai:circuit:open:'.hash('sha256', "{$provider}|{$capability}");
    }

    private function circuitHalfOpenKey(string $provider, string $capability): string
    {
        return 'ai:circuit:half-open:'.hash('sha256', "{$provider}|{$capability}");
    }

    private function handleProviderResourceOverrun(
        AiExecutionRequest $request,
        AiProviderResult $providerResult,
        float $maxCost,
        float $maxCredits,
        string $reservationId,
        array $pricingSnapshot,
        string $claimToken,
        int $latency,
        string $reason,
    ): void {
        $chargedCost = min($providerResult->costUsd, $maxCost);
        $chargedCredits = $this->creditPricing->fromProviderUsdAtSnapshot(
            $chargedCost,
            $pricingSnapshot['credits_per_provider_usd'],
            $pricingSnapshot['minimum_charge'],
        );
        $this->openCircuitImmediately($request->selection->providerKey, $request->context->capability);

        Log::critical('AI provider exceeded an authorized resource policy.', [
            'correlation_id' => $request->context->correlationId,
            'provider' => $request->selection->providerKey,
            'capability' => $request->context->capability,
            'reason' => $reason,
            'model' => $providerResult->model,
            'output_tokens' => $providerResult->outputTokens,
            'max_output_tokens' => $request->maxOutputTokens,
            'provider_cost_usd' => $providerResult->costUsd,
            'charged_cost_usd' => $chargedCost,
            'reserved_cost_usd' => $maxCost,
        ]);

        try {
            $this->executions->stageResourceOverrun(
                $request->context->correlationId,
                $claimToken,
                $providerResult,
                $chargedCost,
                $chargedCredits,
                $pricingSnapshot['version'],
                $pricingSnapshot['credits_per_provider_usd'],
                $pricingSnapshot['minimum_charge'],
                'provider_'.$reason,
                $latency,
            );

            DB::transaction(function () use (
                $request,
                $providerResult,
                $maxCredits,
                $chargedCost,
                $chargedCredits,
                $reservationId,
                $reason,
                $claimToken,
            ): void {
                $this->budget->settle(
                    $request->context->billingTenantId,
                    $maxCredits,
                    $chargedCredits,
                    $reservationId,
                    [
                        'provider' => $request->selection->providerKey,
                        'model' => $providerResult->model,
                        'correlation_id' => $request->context->correlationId,
                        'provider_cost_usd' => $providerResult->costUsd,
                        'charged_cost_usd' => $chargedCost,
                        'resource_overrun' => $reason,
                    ],
                );
                $this->executions->fail(
                    $request->context->correlationId,
                    $claimToken,
                    'provider_'.$reason,
                );
            }, 3);
        } catch (Throwable $exception) {
            $this->logFailure($request, $exception, 'AI resource overrun accounting is pending.');
            throw new AiExecutionFailedException('AI accounting is pending reconciliation.');
        }
    }

    private function openCircuitImmediately(string $provider, string $capability): void
    {
        try {
            $cooldown = max(1, (int) config('ai.circuit.cooldown_seconds', 60));
            $threshold = max(1, (int) config('ai.circuit.failure_threshold', 3));
            Cache::put($this->circuitFailureKey($provider, $capability), $threshold, $cooldown * 4);
            Cache::put(
                $this->circuitOpenKey($provider, $capability),
                now()->addSeconds($cooldown)->timestamp,
                $cooldown,
            );
            Cache::forget($this->circuitHalfOpenKey($provider, $capability));
        } catch (Throwable $exception) {
            Log::error('AI safety circuit could not be opened.', [
                'provider' => $provider,
                'capability' => $capability,
                'exception' => $exception::class,
            ]);
        }
    }

    private function refundAndFail(
        AiExecutionRequest $request,
        float $maxCredits,
        string $reservationId,
        string $claimToken,
        string $failureCode,
    ): void {
        try {
            $this->budget->refund(
                $request->context->billingTenantId,
                $maxCredits,
                $reservationId,
                $failureCode,
            );
        } catch (Throwable $exception) {
            Log::error('AI reservation refund failed.', [
                'correlation_id' => $request->context->correlationId,
                'exception' => $exception::class,
            ]);
        }

        $this->executions->fail($request->context->correlationId, $claimToken, $failureCode);
    }

    private function logFailure(
        AiExecutionRequest $request,
        Throwable $exception,
        string $message = 'AI execution failed.',
    ): void {
        Log::warning($message, [
            'correlation_id' => $request->context->correlationId,
            'provider' => $request->selection->providerKey,
            'capability' => $request->context->capability,
            'exception' => $exception::class,
        ]);
    }
}
