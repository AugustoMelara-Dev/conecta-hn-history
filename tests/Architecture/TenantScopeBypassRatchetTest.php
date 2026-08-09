<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class TenantScopeBypassRatchetTest extends TestCase
{
    /**
     * Existing debt captured on 2026-07-21. Counts are maximums, not targets:
     * removing a bypass is always allowed; adding one requires removing another
     * or moving the operation behind a reviewed tenant-aware gateway.
     *
     * @var array<string, int>
     */
    private const LEGACY_MAXIMUMS = [
        'app/Application/Analytics/TrustedCommercialOutcomeProjector.php' => 2,
        'app/Application/Ai/Knowledge/CatalogKnowledgeIndexer.php' => 3,
        'app/Application/Ai/Conversation/ConversationRuntime.php' => 2,
        'app/Application/Ai/Handoff/AiHandoffService.php' => 1,
        'app/Application/Ai/Registry/AiRegistryLifecycle.php' => 1,
        'app/Application/Availability/AvailabilityRuleWriter.php' => 1,
        'app/Application/Automation/Commands/ResumeDueWorkflowRuns.php' => 1,
        'app/Application/Automation/Commands/RunWorkflowManual.php' => 2,
        'app/Application/Crm/CanonicalCrmEntityWriter.php' => 8,
        'app/Application/Crm/CanonicalOpportunityWriter.php' => 14,
        'app/Application/Imports/OperationalImportService.php' => 18,
        'app/Application/Orders/CanonicalOrderWriter.php' => 8,
        'app/Application/Payments/Actions/RefundOrderPaymentAction.php' => 2,
        'app/Application/Payments/Actions/SettleOrderPaymentAction.php' => 1,
        'app/Application/Products/CanonicalProductWriter.php' => 5,
        'app/Application/Products/MerchantProductCatalogAdapter.php' => 3,
        'app/Application/Quotes/CanonicalQuoteWriter.php' => 14,
        'app/Application/Reservations/CanonicalReservationWriter.php' => 10,
        'app/Application/Sales/Listeners/RestoreStockOnCancellation.php' => 1,
        'app/Application/ServiceCatalog/CanonicalServiceWriter.php' => 5,
        'app/Application/ServiceCatalog/MerchantServiceCatalogAdapter.php' => 1,
        'app/Application/Tenant/Transformers/TenantMicrositeTransformer.php' => 1,
        'app/Console/Commands/RecalculateStatsCommand.php' => 2,
        'app/Console/Commands/ReindexAiKnowledgeCommand.php' => 2,
        'app/Console/Commands/ReconcileCommercialOutcomesCommand.php' => 2,
        'app/Domain/TenantGovernance/Services/TenantOnboardingProgressService.php' => 5,
        'app/Exports/SalesExport.php' => 1,
        'app/Filament/App/Resources/CrmDealResource.php' => 6,
        'app/Filament/App/Pages/Dashboard.php' => 1,
        'app/Filament/App/Pages/ImportExport/ImportHistory.php' => 2,
        'app/Filament/App/Pages/WebToLeadPage.php' => 1,
        'app/Filament/App/Resources/Crm/ActivityResource.php' => 1,
        'app/Filament/App/Resources/CrmDealResource/Pages/CreateCrmDeal.php' => 1,
        'app/Filament/App/Resources/CrmDealResource/Pages/EditCrmDeal.php' => 1,
        'app/Filament/App/Resources/CrmPipelineResource.php' => 2,
        'app/Filament/App/Resources/CrmPipelineResource/RelationManagers/StagesRelationManager.php' => 1,
        'app/Filament/App/Resources/MenuSectionResource.php' => 1,
        'app/Filament/App/Resources/OrderResource.php' => 1,
        'app/Filament/App/Resources/AppointmentResource/Pages/CreateAppointment.php' => 1,
        'app/Filament/App/Resources/ServiceProviderResource.php' => 1,
        'app/Filament/App/Resources/ServiceResource.php' => 1,
        'app/Filament/Resources/PaymentResource.php' => 2,
        'app/Filament/Widgets/FinancialStatsOverview.php' => 2,
        'app/Http/Controllers/Api/V1/CatalogImportController.php' => 4,
        'app/Http/Controllers/Api/V1/TenantAiConfigurationController.php' => 1,
        'app/Http/Controllers/Tenant/OrderReceiptController.php' => 1,
        'app/Infrastructure/AI/Tools/AiToolConfirmationService.php' => 2,
        'app/Infrastructure/AI/EloquentPrivateAiTenantAccessReader.php' => 1,
        'app/Infrastructure/AI/EloquentTenantAgentReader.php' => 3,
        'app/Infrastructure/AI/LaravelCatalogImportPublisher.php' => 1,
        'app/Infrastructure/Billing/EloquentBillingReader.php' => 1,
        'app/Infrastructure/Availability/EloquentAvailabilityQuery.php' => 3,
        'app/Infrastructure/Booking/GuestAppointmentManagementReader.php' => 2,
        'app/Infrastructure/Booking/HttpPublicBookingResponder.php' => 2,
        'app/Infrastructure/Crm/EloquentCrmDefaultPipelineGateway.php' => 4,
        'app/Infrastructure/Crm/EloquentCrmPipelineSummaryGateway.php' => 3,
        'app/Infrastructure/Crm/HttpCrmDealResponder.php' => 17,
        'app/Infrastructure/Crm/HttpCrmPipelineResponder.php' => 9,
        'app/Infrastructure/Directory/DirectoryGuideService.php' => 2,
        'app/Infrastructure/Directory/EloquentDirectoryGuideReader.php' => 1,
        'app/Infrastructure/Directory/EloquentPublicTenantEngagementGateway.php' => 9,
        'app/Infrastructure/Interactions/EloquentLeadFollowUpPlaybook.php' => 1,
        'app/Infrastructure/Listeners/Sales/SendOrderConfirmationNotification.php' => 1,
        'app/Infrastructure/Marketing/EloquentCouponReader.php' => 2,
        'app/Infrastructure/Operations/EloquentOperationalCockpit.php' => 2,
        'app/Infrastructure/Operations/EloquentOperationalKnowledgeHealth.php' => 1,
        'app/Infrastructure/Persistence/EloquentPaymentRepository.php' => 1,
        'app/Infrastructure/Persistence/Queries/AdminProductQuery.php' => 2,
        'app/Infrastructure/Product/EloquentProductReader.php' => 2,
        'app/Infrastructure/Privacy/Dovra/DovraTenantBusinessContextResolver.php' => 1,
        'app/Infrastructure/Public/EloquentTenantMicrositeReader.php' => 8,
        'app/Infrastructure/Public/EloquentTenantStorefrontRepository.php' => 6,
        'app/Infrastructure/Repositories/Analytics/EloquentInteractionRepository.php' => 1,
        'app/Infrastructure/Repositories/Products/EloquentProductBlurhashWriter.php' => 1,
        'app/Infrastructure/Repositories/Products/EloquentProductRatingMetricsWriter.php' => 1,
        'app/Infrastructure/Sales/EloquentOrderReader.php' => 3,
        'app/Infrastructure/Sales/EloquentReportReader.php' => 1,
        'app/Infrastructure/Shared/RequestTenantContext.php' => 1,
        'app/Infrastructure/Tenant/TenantPrivateAgentService.php' => 3,
        'app/Models/Interaction.php' => 1,
        'app/Jobs/ResumeWorkflowJob.php' => 2,
        'app/Models/CrmDeal.php' => 1,
        'app/Models/Tenant.php' => 1,
        'app/Services/CouponValidatorService.php' => 2,
        'app/Services/InventoryManager.php' => 5,
        'app/Support/Tenancy/RequestTenantResolver.php' => 1,
    ];

    #[Test]
    public function tenant_scope_bypasses_cannot_expand_beyond_the_reviewed_baseline(): void
    {
        $root = dirname(__DIR__, 2);
        $actual = $this->bypassCounts($root.'/app', $root);
        $violations = [];

        foreach ($actual as $path => $count) {
            if (! array_key_exists($path, self::LEGACY_MAXIMUMS)) {
                $violations[] = "Unreviewed TenantScope bypass: {$path} ({$count})";

                continue;
            }

            $maximum = self::LEGACY_MAXIMUMS[$path];
            if ($count > $maximum) {
                $violations[] = "TenantScope bypass count increased: {$path} ({$count} > {$maximum})";
            }
        }

        self::assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    #[Test]
    public function scanner_counts_calls_but_ignores_comments(): void
    {
        $source = <<<'PHP'
        <?php
        // withoutGlobalScopes() is documentation, not a bypass.
        $query->withoutGlobalScope(TenantScope::class);
        Model::withoutGlobalScopes()->get();
        PHP;

        self::assertSame(2, $this->countBypassTokens($source));
    }

    /**
     * @return array<string, int>
     */
    private function bypassCounts(string $directory, string $root): array
    {
        $counts = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (! is_string($source)) {
                throw new RuntimeException("Unable to read {$file->getPathname()}");
            }
            $count = $this->countBypassTokens($source);

            if ($count === 0) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $counts[$relativePath] = $count;
        }

        ksort($counts);

        return $counts;
    }

    private function countBypassTokens(string $source): int
    {
        $count = 0;

        foreach (token_get_all($source) as $token) {
            if (
                is_array($token)
                && $token[0] === T_STRING
                && in_array($token[1], ['withoutGlobalScope', 'withoutGlobalScopes'], true)
            ) {
                $count++;
            }
        }

        return $count;
    }
}
