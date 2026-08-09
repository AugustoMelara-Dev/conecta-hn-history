<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\BusinessGraph\BusinessGraphBackfill;
use App\Infrastructure\BusinessGraph\DatabaseBusinessGraphRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Evidence-first validation for the Dovra AI workforce integration.
 *
 * The command is deliberately read-only. Even without --dry-run it never
 * performs a production backfill; the only backfill invocation is explicitly
 * dry-run so this command cannot turn a validation request into a migration.
 */
final class ValidateDovraWorkforceCommand extends Command
{
    protected $signature = 'dovra:validate-workforce
        {--tenant= : Limit runtime checks to one tenant UUID}
        {--dry-run : Make the read-only validation boundary explicit}
        {--strict : Treat blocked external/runtime gates as a non-zero result}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Validate the tenant-scoped Dovra AI workforce evidence chain without mutating production data.';

    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'tenants',
        'tenant_onboarding_states',
        'dovra_business_contexts',
        'dovra_business_facts',
        'dovra_business_edges',
        'ai_agent_definitions',
        'ai_agent_versions',
        'ai_agent_activations',
        'ai_conversations',
        'dovra_agent_action_audits',
        'dovra_agent_outcomes',
        'dovra_conversation_channel_links',
        'global_billing_entitlements',
        'global_billing_lifecycle_events',
    ];

    /** @var list<string> */
    private const REQUIRED_FILES = [
        'app/Application/BusinessGraph/BusinessGraphBackfill.php',
        'app/Application/Ai/Workforce/AgentRoleCatalog.php',
        'app/Application/Ai/Workforce/WorkforceAgentResolver.php',
        'app/Application/Automation/ToolPolicyEngine.php',
        'app/Application/Automation/AgentAction/AgentActionExecutor.php',
        'app/Application/Automation/AgentAction/AgentOutcomeRecorder.php',
        'app/Application/Channels/ConversationChannelBridge.php',
        'app/Application/Channels/ConversationChannelLink.php',
        'app/Application/Channels/ChannelDeliveryService.php',
        'app/Application/Discovery/AgentCapabilityProjection.php',
        'app/Domain/Billing/Global/WorkforceEntitlement.php',
        'app/Http/Controllers/Api/V1/TenantWorkforceController.php',
        'database/migrations/2026_08_02_210000_expand_dovra_business_graph.php',
        'database/migrations/2026_08_02_210100_expand_dovra_agent_outcomes.php',
        'database/migrations/2026_08_02_210200_expand_dovra_conversation_channels.php',
        'database/migrations/2026_08_02_210300_expand_dovra_workforce_activation_requests.php',
        'docs/dovra/product/dovra-global-ai-workforce-os-truth.md',
        'docs/dovra/acceptance-matrix-2026-08-01.md',
        'docs/dovra/launch-gates-2026-08-01.md',
        'docs/dovra/evidence/program-workforce-2026-08-02.md',
    ];

    /**
     * @return int<0, 1>
     */
    public function handle(): int
    {
        $tenantOption = $this->option('tenant');
        $tenantId = is_string($tenantOption) && trim($tenantOption) !== ''
            ? strtolower(trim($tenantOption))
            : null;
        $dryRun = (bool) $this->option('dry-run');
        $strict = (bool) $this->option('strict');
        $checks = [];
        $missingEvidence = [];
        $externalPending = $this->externalPending();

        if ($tenantId !== null && ! Str::isUuid($tenantId)) {
            $this->addCheck(
                $checks,
                'tenant_option',
                'fail',
                'The optional tenant must be a UUID.',
            );
            $missingEvidence[] = 'tenant:valid_uuid';
        } else {
            $this->addCheck(
                $checks,
                'tenant_option',
                'pass',
                $tenantId === null ? 'No tenant filter supplied; checks remain tenant-aware.' : 'Tenant filter accepted.',
            );
        }

        $missingFiles = array_values(array_filter(
            self::REQUIRED_FILES,
            static fn (string $path): bool => ! is_file(base_path($path)),
        ));
        if ($missingFiles !== []) {
            $missingEvidence = array_merge(
                $missingEvidence,
                array_map(static fn (string $path): string => 'file:'.$path, $missingFiles),
            );
        }
        $this->addCheck(
            $checks,
            'repository_evidence',
            $missingFiles === [] ? 'pass' : 'fail',
            $missingFiles === []
                ? 'Required Dovra workforce sources, migrations, claims and evidence files are present.'
                : 'Missing required repository evidence: '.implode(', ', $missingFiles),
        );

        $runtimeAvailable = $this->databaseAvailable();
        if (! $runtimeAvailable) {
            $this->addCheck(
                $checks,
                'database_runtime',
                'blocked',
                'Database runtime is unavailable; schema, tenant scope and backfill checks cannot be promoted to pass.',
            );
            $missingEvidence[] = 'runtime:database_connection_and_migrations';
        } else {
            $this->validateDatabase(
                checks: $checks,
                missingEvidence: $missingEvidence,
                tenantId: $tenantId,
            );
        }

        $this->validateSourceContracts($checks, $missingEvidence);
        $this->validateClaimsBoundary($checks, $missingEvidence);

        $dirtyPathCount = $this->dirtyPathCount();
        if ($dirtyPathCount === null) {
            $this->addCheck(
                $checks,
                'exact_artifact',
                'blocked',
                'Git status could not be inspected; an immutable artifact cannot be certified.',
            );
        } elseif ($dirtyPathCount > 0) {
            $this->addCheck(
                $checks,
                'exact_artifact',
                'blocked',
                "The checkout has {$dirtyPathCount} dirty paths; exact-artifact certification is unavailable.",
            );
        } else {
            $this->addCheck(
                $checks,
                'exact_artifact',
                'pass',
                'The checkout has no dirty paths.',
            );
        }

        $hardMissingEvidence = array_values(array_filter(
            $missingEvidence,
            static fn (string $evidence): bool => ! str_starts_with($evidence, 'runtime:'),
        ));
        $hasFailures = $hardMissingEvidence !== [] || $this->hasCheckStatus($checks, 'fail');
        $hasBlocked = $this->hasCheckStatus($checks, 'blocked');
        $status = $hasFailures ? 'fail' : (($hasBlocked || $externalPending !== []) ? 'blocked' : 'pass');
        $canCertifyExactArtifact = $status === 'pass'
            && $dirtyPathCount === 0
            && $missingEvidence === []
            && $externalPending === [];

        $payload = [
            'schema' => 1,
            'command' => $this->getName(),
            'status' => $status,
            'tenant' => $tenantId,
            'dry_run' => $dryRun,
            'strict' => $strict,
            'checks' => $checks,
            'missing_evidence' => array_values(array_unique($missingEvidence)),
            'external_pending' => $externalPending,
            'dirty_path_count' => $dirtyPathCount,
            'can_certify_exact_artifact' => $canCertifyExactArtifact,
            'safety' => [
                'database_mutations' => false,
                'production_backfill' => false,
                'external_provider_calls' => false,
                'unsupported_public_or_business_claims' => false,
            ],
        ];

        $this->emit($payload);

        if ($hasFailures || ($strict && $status !== 'pass')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array{key:string,status:string,detail:string}>  $checks
     * @param  list<string>  $missingEvidence
     */
    private function validateDatabase(array &$checks, array &$missingEvidence, ?string $tenantId): void
    {
        $missingTables = [];
        foreach (self::REQUIRED_TABLES as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $missingTables[] = $table;
                }
            } catch (Throwable $exception) {
                $this->addCheck($checks, 'schema_runtime', 'blocked', $exception->getMessage());
                $missingEvidence[] = 'runtime:schema_inspection';

                return;
            }
        }

        if ($missingTables !== []) {
            $missingEvidence = array_merge(
                $missingEvidence,
                array_map(static fn (string $table): string => 'table:'.$table, $missingTables),
            );
        }
        $this->addCheck(
            $checks,
            'schema_contract',
            $missingTables === [] ? 'pass' : 'fail',
            $missingTables === []
                ? 'Onboarding, graph, workforce, action, outcome, channel, Discover and billing tables are present.'
                : 'Missing required tables: '.implode(', ', $missingTables),
        );

        if ($missingTables !== []) {
            return;
        }

        try {
            $report = (new BusinessGraphBackfill(new DatabaseBusinessGraphRepository))->run(
                tenantId: $tenantId,
                dryRun: true,
            );
            $this->addCheck(
                $checks,
                'business_graph_backfill',
                'pass',
                sprintf(
                    'Read-only backfill dry-run completed: facts=%d, edges=%d, skipped_sources=%d.',
                    $report->factsWritten,
                    $report->edgesWritten,
                    count($report->skippedSources),
                ),
            );
        } catch (Throwable $exception) {
            $this->addCheck(
                $checks,
                'business_graph_backfill',
                'blocked',
                'Backfill dry-run could not execute: '.$exception->getMessage(),
            );
            $missingEvidence[] = 'runtime:business_graph_backfill_dry_run';
        }

        if ($tenantId !== null) {
            try {
                $tenantExists = DB::table('tenants')->where('id', $tenantId)->exists();
                $this->addCheck(
                    $checks,
                    'tenant_scope',
                    $tenantExists ? 'pass' : 'fail',
                    $tenantExists
                        ? 'The requested tenant exists and all scoped checks use its tenant boundary.'
                        : 'Requested tenant does not exist.',
                );
                if (! $tenantExists) {
                    $missingEvidence[] = 'tenant:existing_'.$tenantId;
                }

                $violations = $this->crossTenantViolations($tenantId);
                $this->addCheck(
                    $checks,
                    'cross_tenant_integrity',
                    $violations === [] ? 'pass' : 'fail',
                    $violations === []
                        ? 'Composite links and outcome/channel lineage remain tenant-aligned.'
                        : 'Cross-tenant references detected: '.implode(', ', $violations),
                );
                if ($violations !== []) {
                    $missingEvidence[] = 'tenant:cross_tenant_integrity';
                }
            } catch (Throwable $exception) {
                $this->addCheck(
                    $checks,
                    'cross_tenant_integrity',
                    'blocked',
                    'Tenant integrity query could not execute: '.$exception->getMessage(),
                );
                $missingEvidence[] = 'runtime:cross_tenant_integrity_query';
            }
        } else {
            $this->addCheck(
                $checks,
                'cross_tenant_integrity',
                'blocked',
                'Supply --tenant=UUID to run the runtime cross-tenant reference check.',
            );
        }
    }

    /**
     * @param  list<array{key:string,status:string,detail:string}>  $checks
     * @param  list<string>  $missingEvidence
     */
    private function validateSourceContracts(array &$checks, array &$missingEvidence): void
    {
        $contracts = [
            'role_resolution' => [
                'files' => [
                    'app/Application/Ai/Workforce/AgentRoleCatalog.php',
                    'app/Application/Ai/Workforce/WorkforceAgentResolver.php',
                ],
                'needles' => ['AgentRoleCatalog::configuration', 'tenantId', 'workforce_role', 'AiRegistryQuery'],
            ],
            'governed_action_outcome' => [
                'files' => [
                    'app/Application/Automation/ToolPolicyEngine.php',
                    'app/Application/Automation/AgentAction/AgentActionExecutor.php',
                    'app/Application/Automation/AgentAction/AgentOutcomeRecorder.php',
                ],
                'needles' => ['tenant_id', 'idempotency', 'payload_hash', 'outcome'],
            ],
            'channel_continuity' => [
                'files' => [
                    'app/Application/Channels/ConversationChannelBridge.php',
                    'app/Application/Channels/ConversationChannelLink.php',
                    'app/Application/Channels/ChannelDeliveryService.php',
                    'database/migrations/2026_08_02_210200_expand_dovra_conversation_channels.php',
                ],
                'needles' => ['tenant_id', 'lineage_idempotency_key', 'payload_hash', 'external_thread_reference'],
            ],
            'discover_billing_entitlement' => [
                'files' => [
                    'app/Application/Discovery/AgentCapabilityProjection.php',
                    'app/Infrastructure/Discovery/CanonicalPublishedOfferSource.php',
                    'app/Domain/Billing/Global/WorkforceEntitlement.php',
                ],
                'needles' => ['agent_capability', 'tenant', 'provider_event_id', 'live_access'],
            ],
            'activation_is_draft_only' => [
                'files' => ['app/Http/Controllers/Api/V1/TenantWorkforceController.php'],
                'needles' => ['draft', 'simulation', 'pending_external_verification', 'dovra_workforce_activation_requests'],
                'forbidden' => [],
            ],
        ];

        foreach ($contracts as $key => $contract) {
            $contents = '';
            $missingFiles = [];
            foreach ($contract['files'] as $file) {
                $path = base_path($file);
                if (! is_file($path)) {
                    $missingFiles[] = $file;

                    continue;
                }

                $fileContents = file_get_contents($path);
                if (! is_string($fileContents)) {
                    $missingFiles[] = $file;

                    continue;
                }
                $contents .= "\n".$fileContents;
            }

            $missingNeedles = array_values(array_filter(
                $contract['needles'],
                static fn (string $needle): bool => ! str_contains($contents, $needle),
            ));
            $forbiddenPresent = array_values(array_filter(
                $contract['forbidden'] ?? [],
                static fn (string $needle): bool => str_contains($contents, $needle),
            ));
            $passed = $missingFiles === [] && $missingNeedles === [] && $forbiddenPresent === [];
            $detail = $passed
                ? 'Required tenant, replay, governance and evidence markers are present.'
                : 'Missing files='.implode(', ', $missingFiles)
                    .'; missing markers='.implode(', ', $missingNeedles)
                    .'; forbidden markers='.implode(', ', $forbiddenPresent);
            $this->addCheck($checks, $key, $passed ? 'pass' : 'fail', $detail);

            foreach ($missingFiles as $file) {
                $missingEvidence[] = 'file:'.$file;
            }
            foreach ($missingNeedles as $needle) {
                $missingEvidence[] = $key.':'.$needle;
            }
            foreach ($forbiddenPresent as $needle) {
                $missingEvidence[] = $key.':forbidden_'.$needle;
            }
        }
    }

    /**
     * @param  list<array{key:string,status:string,detail:string}>  $checks
     * @param  list<string>  $missingEvidence
     */
    private function validateClaimsBoundary(array &$checks, array &$missingEvidence): void
    {
        $files = [
            'docs/dovra/product/dovra-global-ai-workforce-os-truth.md',
            'docs/dovra/evidence/program-workforce-2026-08-02.md',
            'docs/dovra/launch-gates-2026-08-01.md',
        ];
        $requiredMarkers = [
            'external_dependency',
            'unsupported_claim',
            'public production',
            'traction',
            'revenue',
            'investment',
        ];
        $contents = '';
        $missingFiles = [];
        foreach ($files as $file) {
            $path = base_path($file);
            if (! is_file($path)) {
                $missingFiles[] = $file;

                continue;
            }
            $fileContents = file_get_contents($path);
            if (is_string($fileContents)) {
                $contents .= "\n".$fileContents;
            }
        }
        $missingMarkers = array_values(array_filter(
            $requiredMarkers,
            static fn (string $marker): bool => ! str_contains(strtolower($contents), strtolower($marker)),
        ));
        $passed = $missingFiles === [] && $missingMarkers === [];
        $this->addCheck(
            $checks,
            'claims_boundary',
            $passed ? 'pass' : 'fail',
            $passed
                ? 'Truth and launch documents retain explicit external/unsupported claim boundaries.'
                : 'Claims boundary evidence is incomplete: files='.implode(', ', $missingFiles).'; markers='.implode(', ', $missingMarkers),
        );
        foreach ($missingFiles as $file) {
            $missingEvidence[] = 'file:'.$file;
        }
        foreach ($missingMarkers as $marker) {
            $missingEvidence[] = 'claims_boundary:'.$marker;
        }
    }

    /**
     * @return list<string>
     */
    private function crossTenantViolations(string $tenantId): array
    {
        $violations = [];

        $compositeChecks = [
            [
                'table' => 'dovra_workforce_activation_requests as requests',
                'join' => 'ai_agent_versions as versions',
                'id' => 'requests.candidate_agent_version_id',
                'tenant' => 'versions.tenant_id',
                'base_tenant' => 'requests.tenant_id',
                'label' => 'activation_candidate',
            ],
            [
                'table' => 'dovra_workforce_activation_requests as requests',
                'join' => 'ai_release_decisions as decisions',
                'id' => 'requests.release_decision_id',
                'tenant' => 'decisions.tenant_id',
                'base_tenant' => 'requests.tenant_id',
                'label' => 'activation_release',
            ],
            [
                'table' => 'dovra_conversation_channel_links as links',
                'join' => 'ai_conversations as conversations',
                'id' => 'links.conversation_id',
                'tenant' => 'conversations.tenant_id',
                'base_tenant' => 'links.tenant_id',
                'label' => 'channel_conversation',
            ],
            [
                'table' => 'dovra_agent_outcomes as outcomes',
                'join' => 'dovra_agent_action_audits as audits',
                'id' => 'outcomes.audit_id',
                'tenant' => 'audits.tenant_id',
                'base_tenant' => 'outcomes.tenant_id',
                'label' => 'outcome_audit',
            ],
        ];

        foreach ($compositeChecks as $check) {
            $joinAlias = explode(' as ', $check['join'], 2)[1] ?? $check['join'];
            $rows = DB::table($check['table'])
                ->join($check['join'], $joinAlias.'.id', '=', $check['id'])
                ->where($check['base_tenant'], $tenantId)
                ->whereColumn($check['tenant'], '<>', $check['base_tenant'])
                ->limit(5)
                ->get();

            if ($rows->isNotEmpty()) {
                $violations[] = $check['label'];
            }
        }

        return $violations;
    }

    private function databaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function dirtyPathCount(): ?int
    {
        $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open('git status --porcelain=v1', $descriptor, $pipes, base_path());
        if (! is_resource($process)) {
            return null;
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (! is_string($output) || trim($output) === '') {
            return 0;
        }

        return count(array_filter(preg_split('/\R/', trim($output)) ?: []));
    }

    /** @return list<string> */
    private function externalPending(): array
    {
        return [
            'Compatible PHP 8.4/Laravel 13 vendor runtime and a freshly bootstrapped Composer graph.',
            'Certified database migrations, PostgreSQL/RLS restricted-role proof, and cross-tenant runtime evidence.',
            'Live AI/channel/billing providers, credentials, webhooks, DNS, payment endpoints, and public deployment.',
            'Legal, privacy, security/compliance review and any independent certification or audit sign-off.',
            'Managed backup/restore drill, production observability destinations, SLOs, and incident ownership.',
            'Real customer usage, traction, revenue, PMF, or investment-readiness evidence.',
        ];
    }

    /**
     * @param  list<array{key:string,status:string,detail:string}>  $checks
     */
    private function hasCheckStatus(array $checks, string $status): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] === $status) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{key:string,status:string,detail:string}>  $checks
     */
    private function addCheck(array &$checks, string $key, string $status, string $detail): void
    {
        $checks[] = [
            'key' => $key,
            'status' => $status,
            'detail' => $detail,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function emit(array $payload): void
    {
        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        if ((bool) $this->option('json')) {
            $this->line($encoded);

            return;
        }

        $this->line('status: '.$payload['status']);
        $this->line('checks: '.count($payload['checks']));
        $this->line('missing_evidence: '.count($payload['missing_evidence']));
        $this->line('external_pending: '.count($payload['external_pending']));
        $this->line('dirty_path_count: '.($payload['dirty_path_count'] ?? 'unknown'));
        $this->line('can_certify_exact_artifact: '.($payload['can_certify_exact_artifact'] ? 'true' : 'false'));
    }
}
