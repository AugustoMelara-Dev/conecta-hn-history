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
use Illuminate\Support\ServiceProvider;

/**
 * AIAssistantServiceProvider - Registra los 13 AI assistants.
 */
class AIAssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AssistantExecutor::class, function () {
            $executor = new AssistantExecutor();

            $assistants = [
                new DealEmailDraftAssistant(),
                new ContactEnrichmentAssistant(),
                new LeadScoringAssistant(),
                new ProductDescriptionAssistant(),
                new InvoiceMemoAssistant(),
                new RefundJustificationAssistant(),
                new DashboardNarrativeAssistant(),
                new EmailReplyAssistant(),
                new SentimentAssistant(),
                new TranslationAssistant(),
                new TaxCategoryAssistant(),
                new NextBestActionAssistant(),
                new ReportInsightAssistant(),
            ];

            foreach ($assistants as $assistant) {
                $executor->register($assistant);
            }

            return $executor;
        });
    }
}
