<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Markets\TenantMarketResolver;
use App\Domain\Access\Enums\Role;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Scopes\LocationScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TenantAiConfigurationController extends Controller
{
    public function __construct(
        private readonly TenantMarketResolver $markets,
        private readonly TenantContext $tenantContext,
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
        $privateAssignment = $this->assignment(
            audience: 'private',
            tenantId: $tenantId,
            subject: $tenantId.'|'.$userId.'|web',
        );

        $activeAgent = null;
        $webEligible = false;

        if ((bool) config('ai.enabled', false)
            && (bool) config('ai.private.enabled', false)
            && in_array($tenantId, array_map('strval', (array) config('ai.private.tenant_allowlist', [])), true)) {
            $activeAgent = DB::table('ai_agent_activations')
                ->join('ai_agent_versions', function ($join): void {
                    $join->on('ai_agent_versions.id', '=', 'ai_agent_activations.agent_version_id')
                        ->on('ai_agent_versions.tenant_id', '=', 'ai_agent_activations.tenant_id');
                })
                ->join('ai_agent_definitions', function ($join): void {
                    $join->on('ai_agent_definitions.id', '=', 'ai_agent_versions.agent_definition_id')
                        ->on('ai_agent_definitions.tenant_id', '=', 'ai_agent_versions.tenant_id');
                })
                ->where('ai_agent_activations.tenant_id', $tenantId)
                ->where('ai_agent_activations.effective_from', '<=', now())
                ->where(function ($builder): void {
                    $builder
                        ->whereNull('ai_agent_activations.effective_until')
                        ->orWhere('ai_agent_activations.effective_until', '>', now());
                })
                ->whereIn('ai_agent_activations.scope_type', ['tenant_channel', 'tenant', 'capability'])
                ->whereIn('ai_agent_activations.scope_key', [$tenantId.'|web', $tenantId, 'private_tenant_agent'])
                ->whereIn('ai_agent_versions.lifecycle_status', ['candidate', 'eligible', 'active'])
                ->where('ai_agent_versions.kill_switch_enabled', false)
                ->orderByRaw("CASE ai_agent_activations.scope_type WHEN 'tenant_channel' THEN 0 WHEN 'tenant' THEN 1 ELSE 2 END")
                ->orderByDesc('ai_agent_activations.effective_from')
                ->select([
                    'ai_agent_versions.id',
                    'ai_agent_versions.version',
                    'ai_agent_definitions.name',
                ])
                ->first();

            $webEligible = $activeAgent !== null;
        }

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
                    'enabled' => ! (bool) config('ai.enabled', false) || ! (bool) config('ai.private.enabled', false),
                ],
                'canary' => [
                    'private' => [
                        'assignment' => $privateAssignment,
                    ],
                ],
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    private function assignment(string $audience, string $tenantId, string $subject): string
    {
        $percent = max(0, min(100, (int) config("ai.cutover.{$audience}_percent", 0)));
        $salt = (string) config('ai.cutover.cohort_salt', 'dovra-ai-foundation');
        $bucket = hexdec(substr(hash('sha256', $salt.'|'.$tenantId.'|'.$subject.'|'.$audience), 0, 8)) % 100;

        return $bucket < $percent ? 'canonical' : 'compatibility';
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
