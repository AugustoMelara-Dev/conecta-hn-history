<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Conversation\AiFoundationBackfill;
use App\Application\Ai\Registry\AiRegistryQuery;
use App\Application\Ai\Registry\AiRegistryResolver;
use App\Application\Markets\TenantMarketResolver;
use App\Domain\Access\Enums\Role;
use App\Domain\Shared\Services\TenantContext;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Scopes\LocationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenantAiConfigurationController extends Controller
{
    public function __construct(
        private readonly TenantMarketResolver $markets,
        private readonly TenantContext $tenantContext,
        private readonly AiRegistryResolver $registry,
        private readonly AiFoundationBackfill $conversationAuthority,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $userId = $this->userId($request);

        abort_unless($this->hasSupportAccess($tenantId, $userId), 403);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()
            ->withoutGlobalScope(LocationScope::class)
            ->whereKey($tenantId)
            ->firstOrFail();

        $market = $this->markets->resolve($tenant);
        $conversationId = $request->query('conversation_id');
        if ($conversationId !== null && (! is_string($conversationId) || ! Str::isUuid($conversationId))) {
            abort(422, 'conversation_id must be a UUID.');
        }
        $persistedConversation = $conversationId === null
            ? null
            : DB::table('ai_conversations')
                ->where('tenant_id', $tenantId)
                ->where('id', $conversationId)
                ->first();
        abort_if($conversationId !== null && $persistedConversation === null, 404);

        $selection = null;
        $selectionDenied = null;
        $flagsEnabled = (bool) config('ai.enabled', false)
            && (bool) config('ai.private.enabled', false)
            && (bool) config('ai.capabilities.private_tenant_agent.enabled', false)
            && in_array($tenantId, array_map('strval', (array) config('ai.private.tenant_allowlist', [])), true);

        if ($flagsEnabled) {
            try {
                $selection = $this->registry->resolve(new AiRegistryQuery(
                    tenantId: $tenantId,
                    audience: 'private',
                    capability: 'private_tenant_agent',
                    channel: 'web',
                    marketCode: strtoupper((string) $market->countryCode),
                    residencyClass: strtoupper((string) $market->countryCode),
                    actorId: $userId,
                    cohortSubject: $tenantId.'|'.$userId.'|web',
                ));
            } catch (AiExecutionDeniedException $exception) {
                $selectionDenied = $exception->reason;
            }
        }

        $persistedPath = false;
        if ($selection !== null && $persistedConversation !== null) {
            $persistedConversation = $this->conversationAuthority->persistCanaryPath(
                tenantId: $tenantId,
                conversationId: (string) $conversationId,
                agentVersionId: $selection->agentVersionId,
            );
        }

        if ($selection !== null
            && $persistedConversation !== null
            && is_string($persistedConversation->agent_version_id)
            && ! hash_equals((string) $persistedConversation->agent_version_id, $selection->agentVersionId)) {
            // A conversation's first canonical path is immutable. If the
            // registry now resolves elsewhere, fail closed rather than
            // silently switching a retry to a different agent.
            $selectionDenied = 'canary_persisted_path';
            $selection = null;
        }

        $persistedPath = $persistedConversation !== null
            && is_string($persistedConversation->agent_version_id)
            && $selection !== null
            && hash_equals((string) $persistedConversation->agent_version_id, $selection->agentVersionId);

        $activeAgent = $selection === null
            ? null
            : DB::table('ai_agent_versions')
                ->join('ai_agent_definitions', function ($join): void {
                    $join->on('ai_agent_definitions.id', '=', 'ai_agent_versions.agent_definition_id')
                        ->on('ai_agent_definitions.tenant_id', '=', 'ai_agent_versions.tenant_id');
                })
                ->where('ai_agent_versions.tenant_id', $tenantId)
                ->where('ai_agent_versions.id', $selection->agentVersionId)
                ->select([
                    'ai_agent_versions.id',
                    'ai_agent_versions.version',
                    'ai_agent_definitions.name',
                ])
                ->first();
        $webEligible = $selection !== null && $activeAgent !== null;

        $balance = round((float) ($tenant->getAttribute('ai_balance') ?? 0), 2);
        $reserved = round((float) ($tenant->getAttribute('ai_reserved_balance') ?? 0), 2);
        $available = round(max(0, $balance - $reserved), 2);

        return response()->json([
            'data' => [
                'tenant_id' => $tenantId,
                'active_agent' => $activeAgent === null ? null : [
                    'id' => (string) $activeAgent->id,
                    'name' => (string) $activeAgent->name,
                    'version_label' => 'v'.(string) $activeAgent->version,
                ],
                'channel_eligibility' => [
                    'web' => [
                        'enabled' => $webEligible,
                    ],
                ],
                'credit_summary' => [
                    'balance' => $balance,
                    'reserved' => $reserved,
                    'available' => $available,
                ],
                'kill_switch' => [
                    'enabled' => ! $webEligible,
                    'reason' => $webEligible ? null : ($selectionDenied ?? 'configuration_unavailable'),
                ],
                'canary' => [
                    'private' => [
                        'assignment' => $webEligible ? 'canonical' : 'compatibility',
                        'stickiness' => 'deterministic_registry_cohort',
                        'persisted_path' => $persistedPath,
                    ],
                ],
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    private function hasSupportAccess(string $tenantId, string $userId): bool
    {
        $tenantOwnerId = DB::table('tenants')->where('id', $tenantId)->value('user_id');
        if (is_string($tenantOwnerId) && hash_equals($tenantOwnerId, $userId)) {
            return true;
        }

        $role = DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNotNull('accepted_at')
            ->whereNull('revoked_at')
            ->whereNull('suspended_at')
            ->value('role');

        return in_array($role, [
            Role::OWNER->value,
            Role::ADMIN->value,
            Role::OPERATOR->value,
        ], true);
    }

    private function tenantId(): string
    {
        if (! $this->tenantContext->isInitialized() || $this->tenantContext->isRoot()) {
            abort(403);
        }

        return $this->tenantContext->id();
    }

    private function userId(Request $request): string
    {
        $user = $request->user();
        abort_unless(is_object($user), 401);

        return (string) $user->getAuthIdentifier();
    }
}
