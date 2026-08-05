<?php

declare(strict_types=1);

namespace App\Application\Crm;

// @withoutGlobalScopes-safety CANONICAL_WRITER_TENANT_FILTER: every bypass is constrained by command tenant ownership.

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Crm\Commands\CreateOpportunityCommand;
use App\Application\Crm\Commands\MoveOpportunityStageCommand;
use App\Application\Crm\Commands\UpdateOpportunityCommand;
use App\Application\Markets\MarketContext;
use App\Application\Markets\MissingMarketContextException;
use App\Application\Markets\TenantMarketResolver;
use App\Application\TenantGovernance\Crm\CrmDefaultPipelineService;
use App\Domain\Access\Enums\Permission;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CustomerProfile;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative writer for CRM opportunity instances.
 */
final readonly class CanonicalOpportunityWriter
{
    public function __construct(
        private TenantPermissionGuardInterface $permissionGuard,
        private TenantMarketResolver $marketResolver,
        private CrmDefaultPipelineService $defaultPipeline,
        private OutboxRepositoryInterface $outbox,
    ) {}

    public function create(CreateOpportunityCommand $command): OpportunityWriteResult
    {
        $this->assertCreateCommand($command);

        return DB::transaction(function () use ($command): OpportunityWriteResult {
            [$tenant, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CRM_DEALS_CREATE,
            );
            $market = $this->market($tenant);
            $currency = strtoupper($command->currency ?? $market->currencyCode);
            if ($currency !== strtoupper($market->currencyCode)) {
                throw ValidationException::withMessages([
                    'currency' => ['Currency is not supported by the tenant market.'],
                ]);
            }
            $minorUnit = $this->minorUnit($currency);
            $pipeline = $this->pipeline($tenant, $command->pipelineId);
            $stage = $this->stage($tenant, $pipeline, $command->stageId);
            $references = $this->references(
                $tenant,
                $command->customerProfileId,
                $command->leadId,
                $command->ownerUserId,
                $command->locationId,
            );
            $payloadHash = $this->hash([
                'title' => trim($command->title),
                'value_minor' => $command->valueMinor,
                'currency' => $currency,
                'pipeline_id' => (int) $pipeline->id,
                'stage_id' => (int) $stage->id,
                ...$references,
                'source' => $command->source,
                'expected_close_date' => $this->date($command->expectedCloseDate),
                'lost_reason' => $command->lostReason,
                'notes' => $command->notes,
            ]);

            $existing = CrmDeal::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->where('write_idempotency_key', $command->idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof CrmDeal) {
                if (! hash_equals((string) $existing->write_payload_hash, $payloadHash)) {
                    throw new \LogicException('Opportunity idempotency key was reused with an incompatible payload.');
                }

                return new OpportunityWriteResult($existing, true);
            }

            [$status, $closedAt] = $this->lifecycleForStage($stage);
            $deal = new CrmDeal;
            $deal->forceFill([
                'tenant_id' => $command->tenantId,
                'crm_pipeline_id' => $pipeline->id,
                'crm_stage_id' => $stage->id,
                ...$references,
                'title' => trim($command->title),
                'value' => $this->legacyDecimal($command->valueMinor, $minorUnit),
                'currency' => $currency,
                'status' => $status,
                'source' => $command->source,
                'expected_close_at' => $this->date($command->expectedCloseDate),
                'closed_at' => $closedAt,
                'lost_reason' => $status === CrmDeal::STATUS_LOST ? $command->lostReason : null,
                'notes' => $command->notes,
                'write_idempotency_key' => $command->idempotencyKey,
                'write_payload_hash' => $payloadHash,
            ])->save();

            $this->audit($actor, $deal, 'opportunity.created', [
                'tenant_id' => $command->tenantId,
                'currency' => $currency,
                'status' => $status,
            ]);

            return new OpportunityWriteResult($deal);
        }, 3);
    }

    public function update(UpdateOpportunityCommand $command): OpportunityWriteResult
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || $command->dealId < 1) {
            throw new \InvalidArgumentException('Opportunity update requires tenant, actor, and deal identifiers.');
        }

        return DB::transaction(function () use ($command): OpportunityWriteResult {
            [$tenant, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CRM_DEALS_UPDATE,
            );
            $deal = $this->deal($command->tenantId, $command->dealId);
            $attributes = [];

            if ($command->title !== null) {
                if (trim($command->title) === '') {
                    throw ValidationException::withMessages(['title' => ['Title cannot be empty.']]);
                }
                $attributes['title'] = trim($command->title);
            }

            if ($command->valueMinor !== null || $command->currency !== null) {
                $currency = strtoupper($command->currency ?? (string) $deal->currency);
                if ($command->currency !== null) {
                    $market = $this->market($tenant);
                    if ($currency !== strtoupper($market->currencyCode)) {
                        throw ValidationException::withMessages([
                            'currency' => ['Currency is not supported by the tenant market.'],
                        ]);
                    }
                }
                $valueMinor = $command->valueMinor
                    ?? $this->decimalToMinor((string) $deal->getRawOriginal('value'), $this->minorUnit($currency));
                if ($valueMinor < 0) {
                    throw ValidationException::withMessages(['value_minor' => ['Value cannot be negative.']]);
                }
                $attributes['currency'] = $currency;
                $attributes['value'] = $this->legacyDecimal($valueMinor, $this->minorUnit($currency));
            }

            $referenceChanges = [
                'customer_profile_id' => [$command->customerProfileSpecified, $command->customerProfileId, CustomerProfile::class],
                'lead_id' => [$command->leadSpecified, $command->leadId, Lead::class],
                'location_id' => [$command->locationSpecified, $command->locationId, Location::class],
            ];
            foreach ($referenceChanges as $field => [$specified, $id, $model]) {
                if ($specified) {
                    $attributes[$field] = $id === null
                        ? null
                        : $this->tenantRecordId($command->tenantId, $model, $id);
                }
            }
            if ($command->ownerSpecified) {
                $attributes['owner_user_id'] = $command->ownerUserId === null
                    ? null
                    : $this->ownerId($tenant, $command->ownerUserId);
            }
            foreach ([
                'source' => [$command->sourceSpecified, $command->source],
                'lost_reason' => [$command->lostReasonSpecified, $command->lostReason],
                'notes' => [$command->notesSpecified, $command->notes],
            ] as $field => [$specified, $value]) {
                if ($specified) {
                    $attributes[$field] = $value;
                }
            }
            if ($command->expectedCloseDateSpecified) {
                $attributes['expected_close_at'] = $this->date($command->expectedCloseDate);
            }

            $deal->forceFill($attributes)->save();
            $this->audit($actor, $deal, 'opportunity.updated', [
                'tenant_id' => $command->tenantId,
                'status' => (string) $deal->status,
            ]);

            return new OpportunityWriteResult($deal);
        }, 3);
    }

    public function moveStage(MoveOpportunityStageCommand $command): OpportunityWriteResult
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || $command->dealId < 1
            || $command->stageId < 1) {
            throw new \InvalidArgumentException('Stage movement requires tenant, actor, deal, and stage identifiers.');
        }

        return DB::transaction(function () use ($command): OpportunityWriteResult {
            [, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CRM_DEALS_MOVE_STAGE,
            );
            $deal = $this->deal($command->tenantId, $command->dealId);
            $stage = CrmStage::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->where('crm_pipeline_id', $deal->crm_pipeline_id)
                ->lockForUpdate()
                ->findOrFail($command->stageId);
            [$status, $closedAt] = $this->lifecycleForStage($stage, $deal);
            $deal->forceFill([
                'crm_stage_id' => $stage->id,
                'status' => $status,
                'closed_at' => $closedAt,
                'lost_reason' => $status === CrmDeal::STATUS_LOST ? $command->lostReason : null,
            ])->save();
            $this->audit($actor, $deal, 'opportunity.stage_moved', [
                'tenant_id' => $command->tenantId,
                'stage_id' => (int) $stage->id,
                'status' => $status,
            ]);

            return new OpportunityWriteResult($deal);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function convertLead(
        string $tenantId,
        string $actorId,
        int $leadId,
        string $idempotencyKey,
        array $overrides = [],
    ): OpportunityWriteResult {
        $allowed = [
            'pipeline_id', 'stage_id', 'title', 'value_minor', 'currency',
            'owner_user_id', 'location_id', 'expected_close_date', 'notes', 'source',
            'customer_profile_id',
        ];
        $unknown = array_diff(array_keys($overrides), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'overrides' => ['Unknown opportunity override fields: '.implode(', ', $unknown).'.'],
            ]);
        }

        return DB::transaction(function () use (
            $tenantId,
            $actorId,
            $leadId,
            $idempotencyKey,
            $overrides,
        ): OpportunityWriteResult {
            $this->authority($tenantId, $actorId, Permission::CRM_DEALS_CREATE);
            $lead = Lead::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($leadId);
            $existing = CrmDeal::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('lead_id', $leadId)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof CrmDeal && $existing->write_idempotency_key !== $idempotencyKey) {
                return new OpportunityWriteResult($existing, true);
            }
            $customerProfileId = isset($overrides['customer_profile_id'])
                ? (int) $overrides['customer_profile_id']
                : $this->customerProfileForLead($tenantId, $lead)?->getKey();

            $result = $this->create(new CreateOpportunityCommand(
                tenantId: $tenantId,
                actorId: $actorId,
                idempotencyKey: $idempotencyKey,
                title: (string) ($overrides['title'] ?? "Oportunidad: {$lead->name}"),
                valueMinor: (int) ($overrides['value_minor'] ?? 0),
                currency: isset($overrides['currency']) ? (string) $overrides['currency'] : null,
                pipelineId: isset($overrides['pipeline_id']) ? (int) $overrides['pipeline_id'] : null,
                stageId: isset($overrides['stage_id']) ? (int) $overrides['stage_id'] : null,
                customerProfileId: $customerProfileId === null ? null : (int) $customerProfileId,
                leadId: (int) $lead->id,
                ownerUserId: isset($overrides['owner_user_id']) ? (string) $overrides['owner_user_id'] : null,
                locationId: isset($overrides['location_id']) ? (string) $overrides['location_id'] : null,
                source: (string) ($overrides['source'] ?? $lead->source ?? 'lead_conversion'),
                expectedCloseDate: isset($overrides['expected_close_date'])
                    ? (string) $overrides['expected_close_date']
                    : null,
                notes: (string) ($overrides['notes'] ?? $lead->message ?? ''),
            ));
            $lead->forceFill([
                'status' => 'converted',
                'customer_profile_id' => $result->deal->customer_profile_id,
            ])->save();

            return $result;
        }, 3);
    }

    public function archive(string $tenantId, string $actorId, int $dealId): void
    {
        DB::transaction(function () use ($tenantId, $actorId, $dealId): void {
            [, $actor] = $this->authority($tenantId, $actorId, Permission::CRM_DEALS_DELETE);
            $deal = $this->deal($tenantId, $dealId);
            $this->audit($actor, $deal, 'opportunity.archived', [
                'tenant_id' => $tenantId,
                'status' => (string) $deal->status,
            ]);
            $deal->delete();
        }, 3);
    }

    private function assertCreateCommand(CreateOpportunityCommand $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || trim($command->idempotencyKey) === ''
            || mb_strlen($command->idempotencyKey) > 160
            || trim($command->title) === '') {
            throw new \InvalidArgumentException(
                'Opportunity creation requires tenant, actor, idempotency key, and title.',
            );
        }
        if ($command->valueMinor < 0) {
            throw ValidationException::withMessages(['value_minor' => ['Value cannot be negative.']]);
        }
    }

    /**
     * @return array{Tenant, User}
     */
    private function authority(string $tenantId, string $actorId, Permission $permission): array
    {
        if (! Str::isUuid($tenantId) || ! Str::isUuid($actorId)) {
            throw new \InvalidArgumentException('CRM authority requires UUID tenant and actor identifiers.');
        }
        $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($tenantId);
        $actor = User::query()->withoutGlobalScopes()->findOrFail($actorId);
        if (! $this->permissionGuard->allows(
            $actor,
            $tenant,
            Capability::CRM_PIPELINE_MANAGEMENT,
            $permission,
        )) {
            throw new AuthorizationException('Actor cannot mutate CRM opportunities for this tenant.');
        }

        return [$tenant, $actor];
    }

    private function pipeline(Tenant $tenant, ?int $pipelineId): CrmPipeline
    {
        if ($pipelineId === null) {
            return $this->defaultPipeline->ensureForTenant($tenant);
        }

        return CrmPipeline::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->findOrFail($pipelineId);
    }

    private function stage(Tenant $tenant, CrmPipeline $pipeline, ?int $stageId): CrmStage
    {
        $query = CrmStage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('crm_pipeline_id', $pipeline->id);

        if ($stageId !== null) {
            return $query->lockForUpdate()->findOrFail($stageId);
        }

        return (clone $query)->where('is_default', true)->lockForUpdate()->first()
            ?? $query->orderBy('sort_order')->lockForUpdate()->firstOrFail();
    }

    /**
     * @return array{
     *   customer_profile_id: int|null,
     *   lead_id: int|null,
     *   owner_user_id: string|null,
     *   location_id: string|null
     * }
     */
    private function references(
        Tenant $tenant,
        ?int $customerProfileId,
        ?int $leadId,
        ?string $ownerUserId,
        ?string $locationId,
    ): array {
        return [
            'customer_profile_id' => $customerProfileId === null
                ? null
                : $this->tenantRecordId((string) $tenant->id, CustomerProfile::class, $customerProfileId),
            'lead_id' => $leadId === null
                ? null
                : $this->tenantRecordId((string) $tenant->id, Lead::class, $leadId),
            'owner_user_id' => $ownerUserId === null ? null : $this->ownerId($tenant, $ownerUserId),
            'location_id' => $locationId === null
                ? null
                : $this->tenantRecordId((string) $tenant->id, Location::class, $locationId),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function tenantRecordId(string $tenantId, string $modelClass, int|string $id): int|string
    {
        return $modelClass::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id)
            ->getKey();
    }

    private function ownerId(Tenant $tenant, string $userId): string
    {
        if (! Str::isUuid($userId)) {
            throw ValidationException::withMessages(['owner_user_id' => ['Owner must be a UUID.']]);
        }
        User::query()->withoutGlobalScopes()->findOrFail($userId);
        $isOwner = (string) $tenant->user_id === $userId;
        $isMember = DB::table('tenant_memberships')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNotNull('accepted_at')
            ->whereNull('suspended_at')
            ->whereNull('revoked_at')
            ->exists();
        if (! $isOwner && ! $isMember) {
            throw ValidationException::withMessages([
                'owner_user_id' => ['Opportunity owner must be an active tenant member.'],
            ]);
        }

        return $userId;
    }

    private function customerProfileForLead(string $tenantId, Lead $lead): ?CustomerProfile
    {
        if ($lead->customer_profile_id !== null) {
            return CustomerProfile::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($lead->customer_profile_id);
        }

        $query = CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId);
        $normalizedEmail = filled($lead->email) ? strtolower(trim((string) $lead->email)) : null;
        $normalizedPhone = filled($lead->phone)
            ? preg_replace('/\D+/', '', (string) $lead->phone)
            : null;
        $existing = $normalizedEmail === null
            ? null
            : (clone $query)->where('normalized_email', $normalizedEmail)->first();
        if (! $existing instanceof CustomerProfile && $normalizedPhone !== null) {
            $existing = (clone $query)->where('normalized_phone', $normalizedPhone)->first();
        }
        if ($existing instanceof CustomerProfile) {
            return $existing;
        }
        if (blank($lead->name) && $normalizedEmail === null && $normalizedPhone === null) {
            return null;
        }

        return CustomerProfile::query()->withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenantId,
            'normalized_email' => $normalizedEmail,
            'normalized_phone' => $normalizedPhone,
            'name' => (string) ($lead->name ?: 'Lead sin nombre'),
            'email' => $lead->email,
            'phone' => $lead->phone,
            'preferred_channel' => filled($lead->phone) ? 'whatsapp' : 'email',
            'lifecycle_stage' => CustomerProfile::LIFECYCLE_PROSPECT,
            'customer_segment' => CustomerProfile::SEGMENT_PROSPECT,
            'acquisition_channel' => $lead->source,
            'last_lead_at' => $lead->created_at,
        ]);
    }

    private function deal(string $tenantId, int $dealId): CrmDeal
    {
        return CrmDeal::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->findOrFail($dealId);
    }

    /**
     * @return array{string, CarbonImmutable|null}
     */
    private function lifecycleForStage(CrmStage $stage, ?CrmDeal $deal = null): array
    {
        return match ($stage->type) {
            CrmStage::TYPE_WON => [CrmDeal::STATUS_WON, $deal?->closed_at?->toImmutable() ?? now()->toImmutable()],
            CrmStage::TYPE_LOST => [CrmDeal::STATUS_LOST, $deal?->closed_at?->toImmutable() ?? now()->toImmutable()],
            default => [CrmDeal::STATUS_OPEN, null],
        };
    }

    private function date(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (\Throwable) {
            $date = null;
        }
        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([
                'expected_close_date' => ['Expected close date must use YYYY-MM-DD.'],
            ]);
        }

        return $value;
    }

    private function minorUnit(string $currency): int
    {
        $minorUnit = DB::table('currencies')->where('code', $currency)->value('minor_unit');
        if ($minorUnit === null || (int) $minorUnit < 0 || (int) $minorUnit > 3) {
            throw ValidationException::withMessages(['currency' => ['Currency is not configured.']]);
        }

        return (int) $minorUnit;
    }

    private function market(Tenant $tenant): MarketContext
    {
        try {
            return $this->marketResolver->resolve($tenant);
        } catch (MissingMarketContextException) {
            throw ValidationException::withMessages([
                'market' => ['Tenant market context is required for CRM money.'],
            ]);
        }
    }

    private function legacyDecimal(int $minor, int $minorUnit): string
    {
        return number_format($minor / (10 ** $minorUnit), 2, '.', '');
    }

    private function decimalToMinor(string $decimal, int $minorUnit): int
    {
        return (int) round((float) $decimal * (10 ** $minorUnit));
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @param  array<string, scalar|null>  $properties
     */
    private function audit(User $actor, CrmDeal $deal, string $event, array $properties): void
    {
        activity('business-operations')
            ->performedOn($deal)
            ->causedBy($actor)
            ->withProperties($properties)
            ->event($event)
            ->log($event);

        $this->outbox->append(new BusinessOperationChanged(
            aggregateId: 'crm_deal:'.$deal->getKey(),
            aggregateType: 'crm_deal',
            tenantId: (string) ($properties['tenant_id'] ?? ''),
            actorId: (string) $actor->getKey(),
            eventName: $event,
            attributes: $properties,
            occurredAt: now()->toImmutable(),
        ));
    }
}
