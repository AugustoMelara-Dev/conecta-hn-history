<?php

declare(strict_types=1);

namespace App\Application\Ai\Knowledge;

use App\Domain\Billing\Services\AiCreditPricingService;
use App\Infrastructure\AI\BudgetEnforcer;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class EmbeddingUsageMeter
{
    public function __construct(
        private EmbeddingProvider $provider,
        private BudgetEnforcer $budget,
        private AiCreditPricingService $pricing,
    ) {}

    public function embed(string $billingTenantId, string $operation, string $content): EmbeddingResult
    {
        if (! config('ai.knowledge.metering_enabled', false)) {
            return $this->provider->embed($content);
        }

        $maximumProviderCost = (float) config('ai.knowledge.max_cost_usd', 0.001);
        $inputRate = (float) config('ai.knowledge.input_usd_per_million', 0.02);
        if ($maximumProviderCost <= 0 || ! is_finite($maximumProviderCost)
            || $inputRate <= 0 || ! is_finite($inputRate)) {
            throw new \LogicException('Embedding cost controls are invalid.');
        }

        $estimatedCredits = $this->pricing->fromProviderUsd($maximumProviderCost);
        $reservationId = $this->budget->reserve($billingTenantId, $estimatedCredits, $operation);

        try {
            $result = $this->provider->embed($content);
            $providerCost = ($result->inputTokens * $inputRate) / 1_000_000;
            $snapshot = $this->budget->pricingSnapshot($billingTenantId, $reservationId);
            $actualCredits = $this->pricing->fromProviderUsdAtSnapshot(
                $providerCost,
                $snapshot['credits_per_provider_usd'],
                $snapshot['minimum_charge'],
            );
            $this->budget->settle(
                $billingTenantId,
                $estimatedCredits,
                $actualCredits,
                $reservationId,
                [
                    'provider_model' => $result->model,
                    'input_tokens' => $result->inputTokens,
                    'provider_cost_usd' => $providerCost,
                ],
            );

            return $result;
        } catch (Throwable $exception) {
            try {
                $this->budget->refund(
                    $billingTenantId,
                    $estimatedCredits,
                    $reservationId,
                    'embedding_provider_failure',
                );
            } catch (Throwable $refundException) {
                Log::critical('Embedding credit refund failed.', [
                    'tenant_id' => $billingTenantId,
                    'reservation_id' => $reservationId,
                    'exception_class' => $refundException::class,
                ]);
            }

            throw $exception;
        }
    }
}
