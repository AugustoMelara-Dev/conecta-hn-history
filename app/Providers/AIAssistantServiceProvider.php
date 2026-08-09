<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Ai\AssistantExecutor;
use App\Application\Ai\Assistants\ContactEnrichmentAssistant;
use App\Application\Ai\Assistants\DashboardNarrativeAssistant;
use App\Application\Ai\Assistants\DealEmailDraftAssistant;
use App\Application\Ai\Assistants\EmailReplyAssistant;
use App\Application\Ai\Assistants\InvoiceMemoAssistant;
use App\Application\Ai\Assistants\LeadScoringAssistant;
use App\Application\Ai\Assistants\NextBestActionAssistant;
use App\Application\Ai\Assistants\ProductDescriptionAssistant;
use App\Application\Ai\Assistants\RefundJustificationAssistant;
use App\Application\Ai\Assistants\ReportInsightAssistant;
use App\Application\Ai\Assistants\SentimentAssistant;
use App\Application\Ai\Assistants\TaxCategoryAssistant;
use App\Application\Ai\Assistants\TranslationAssistant;
use App\Application\Ai\Contracts\AssistantRuntime;
use App\Infrastructure\AI\CanonicalAssistantRuntime;
use Illuminate\Support\ServiceProvider;

/**
 * AIAssistantServiceProvider - Registra los 13 AI assistants.
 */
class AIAssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AssistantRuntime::class, CanonicalAssistantRuntime::class);

        $this->app->singleton(AssistantExecutor::class, function ($app) {
            $executor = new AssistantExecutor;

            $assistants = [
                DealEmailDraftAssistant::class,
                ContactEnrichmentAssistant::class,
                LeadScoringAssistant::class,
                ProductDescriptionAssistant::class,
                InvoiceMemoAssistant::class,
                RefundJustificationAssistant::class,
                DashboardNarrativeAssistant::class,
                EmailReplyAssistant::class,
                SentimentAssistant::class,
                TranslationAssistant::class,
                TaxCategoryAssistant::class,
                NextBestActionAssistant::class,
                ReportInsightAssistant::class,
            ];

            foreach ($assistants as $assistantClass) {
                $executor->register($app->make($assistantClass));
            }

            return $executor;
        });
    }
}
