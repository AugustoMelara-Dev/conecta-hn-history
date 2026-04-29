<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessAi;

use App\Exceptions\InsufficientAiCreditsException;
use App\Infrastructure\AI\BudgetEnforcer;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class TenantPrivateAnswerGenerator
{
    public function __construct(
        private readonly PrivateRagRedactor $redactor,
        private readonly BudgetEnforcer $budgetEnforcer,
    ) {}

    /**
     * @param  list<array<string,mixed>>  $sources
     */
    public function generate(Tenant $tenant, string $query, array $sources, ?string $degradedReason, ?string $userId): string
    {
        if ($sources === []) {
            return 'No encontré información privada indexada para responder esa pregunta. Reindexa el conocimiento del negocio y vuelve a intentar.';
        }

        $apiKey = (string) config('services.openai.api_key', '');
        if (trim($apiKey) === '' || ! (bool) config('ai-rag.private.llm_enabled', true)) {
            return $this->deterministicAnswer($query, $sources, $degradedReason);
        }

        $context = collect($sources)
            ->map(static fn (array $source): string => '- '.$source['title'].': '.$source['excerpt'])
            ->implode("\n");

        $estimatedCost = max(0.000001, (float) config('ai-rag.private.estimated_chat_cost_usd', 0.002));
        $reservationId = null;
        $model = (string) config('ai-rag.private.chat_model', 'gpt-4o-mini');
        $maxTokens = max(128, (int) config('ai-rag.private.max_completion_tokens', 700));

        try {
            $reservationId = $this->budgetEnforcer->reserve(
                tenantId: (string) $tenant->getKey(),
                estimatedCost: $estimatedCost,
                operation: 'business_private_rag_chat'
            );

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(max(5, (int) config('ai-rag.private.openai_timeout_seconds', 12)))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres el copiloto privado de un negocio en Vito Business OS. Responde solo con el contexto privado entregado. Si falta información, dilo. No inventes datos, no reveles emails, teléfonos, direcciones ni identificadores internos.',
                        ],
                        [
                            'role' => 'user',
                            'content' => "Negocio: {$tenant->name}\nPregunta: {$query}\nContexto privado con redacción segura:\n{$context}",
                        ],
                    ],
                ]);

            if ($response->ok()) {
                $answer = trim((string) $response->json('choices.0.message.content', ''));
                if ($answer !== '') {
                    $usage = (array) $response->json('usage', []);
                    $inputTokens = max(0, (int) ($usage['prompt_tokens'] ?? 0));
                    $outputTokens = max(0, (int) ($usage['completion_tokens'] ?? 0));
                    $actualCost = $this->calculateChatCost($inputTokens, $outputTokens);

                    $this->budgetEnforcer->settle(
                        tenantId: (string) $tenant->getKey(),
                        estimatedCost: $estimatedCost,
                        actualCost: $actualCost,
                        reservationId: $reservationId,
                        usageMetadata: [
                            'model' => $model,
                            'input_tokens' => $inputTokens,
                            'output_tokens' => $outputTokens,
                            'operation' => 'business_private_rag_chat',
                        ]
                    );

                    $this->logAiUsage($tenant, $userId, $model, $inputTokens, $outputTokens, $actualCost);

                    return $this->redactor->redactText($answer);
                }
            }
        } catch (InsufficientAiCreditsException $exception) {
            Log::warning('business_rag.llm_budget_insufficient', [
                'tenant_id' => (string) $tenant->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return $this->deterministicAnswer($query, $sources, 'ai_budget_insufficient');
        } catch (\Throwable $exception) {
            if (is_string($reservationId)) {
                $this->budgetEnforcer->refund(
                    tenantId: (string) $tenant->getKey(),
                    estimatedCost: $estimatedCost,
                    reservationId: $reservationId,
                    failureReason: $exception->getMessage()
                );
            }

            Log::warning('business_rag.llm_failed', [
                'tenant_id' => (string) $tenant->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        if (is_string($reservationId)) {
            $this->budgetEnforcer->refund(
                tenantId: (string) $tenant->getKey(),
                estimatedCost: $estimatedCost,
                reservationId: $reservationId,
                failureReason: 'business_rag_llm_empty_or_error'
            );
        }

        return $this->deterministicAnswer($query, $sources, $degradedReason ?? 'llm_unavailable');
    }

    private function calculateChatCost(int $inputTokens, int $outputTokens): float
    {
        $inputPerMillion = (float) config('ai-rag.private.chat_input_cost_per_1m_tokens', 0.15);
        $outputPerMillion = (float) config('ai-rag.private.chat_output_cost_per_1m_tokens', 0.60);

        return round(($inputTokens / 1_000_000 * $inputPerMillion) + ($outputTokens / 1_000_000 * $outputPerMillion), 6);
    }

    private function logAiUsage(Tenant $tenant, ?string $userId, string $model, int $inputTokens, int $outputTokens, float $actualCost): void
    {
        if ($userId === null || ! Schema::hasTable('ai_usage_logs')) {
            return;
        }

        DB::table('ai_usage_logs')->insert([
            'user_id' => $userId,
            'tenant_id' => (string) $tenant->getKey(),
            'provider' => 'openai',
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $actualCost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string,mixed>>  $sources
     */
    private function deterministicAnswer(string $query, array $sources, ?string $degradedReason): string
    {
        $head = array_slice($sources, 0, 3);
        $lines = array_map(static fn (array $source): string => sprintf('%s: %s', $source['title'], $source['excerpt']), $head);
        $suffix = $degradedReason ? "\n\nNota operativa: respuesta generada en modo {$degradedReason}." : '';

        return "Según los datos privados del negocio para: {$query}\n".implode("\n", $lines).$suffix;
    }
}
