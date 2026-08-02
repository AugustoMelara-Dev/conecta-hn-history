<?php

declare(strict_types=1);

namespace App\Application\Privacy\Dovra;

use App\Domain\Privacy\Dovra\DataEnvelope;
use App\Domain\Privacy\Dovra\PrivacyRequestResult;
use App\Infrastructure\Privacy\Dovra\DatabasePrivacyEvidenceStore;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Infrastructure\Privacy\Dovra\LegacyAccountPrivacyAdapter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PrivacyRequestService
{
    public function __construct(
        private readonly LegacyAccountPrivacyAdapter $legacyPrivacy,
        private readonly DataClassificationPolicy $policy,
        private readonly DatabasePrivacyEvidenceStore $store,
        private readonly DovraIntegrationSeamRegistry $seams,
        private readonly RetentionLifecycleService $retention,
    ) {}

    public function export(User $actor, User $subject, ?Tenant $tenant, string $idempotencyKey): PrivacyRequestResult
    {
        $tenantId = $this->authorize($actor, $subject, $tenant);

        if ($existing = $this->store->findRequestByKey(
            idempotencyKey: $idempotencyKey,
            tenantId: $tenantId,
            actorId: (string) $actor->getKey(),
            subjectType: User::class,
            subjectId: (string) $subject->getKey(),
            requestType: 'export',
        )) {
            return $this->resultFromRow($existing);
        }

        $requestId = $this->store->beginRequest(
            requestType: 'export',
            idempotencyKey: $idempotencyKey,
            tenantId: $tenantId,
            actorId: (string) $actor->getKey(),
            subjectType: User::class,
            subjectId: (string) $subject->getKey(),
        );

        $payload = $this->legacyPrivacy->export($subject);
        $decision = $this->policy->classifyAndValidate(new DataEnvelope(
            sourceSystem: 'export',
            payload: $payload,
            tenantId: $tenantId,
            actorId: (string) $actor->id,
            channel: 'privacy_request',
        ));

        if (! $decision->allowed) {
            throw ValidationException::withMessages([
                'export' => 'The export payload failed the Dovra privacy policy.',
            ]);
        }

        $summary = [
            'format' => 'dovra-account-export-v2',
            'legacy_format' => (string) ($payload['format'] ?? 'conecta-hn-account-export-v1'),
            'generated_at' => now()->toIso8601String(),
            'profile' => $payload['profile'] ?? [],
            'datasets' => array_diff_key($payload, array_flip(['format', 'generated_at', 'profile'])),
            'integration_seams' => $this->seams->keys(),
        ];

        $this->store->appendEvent($requestId, 'request_created', [
            'classification' => $decision->classification,
            'retention_category' => $decision->retentionCategory,
        ]);
        $this->store->completeRequest($requestId, $summary);
        $this->store->appendEvent($requestId, 'request_completed', [
            'format' => $summary['format'],
            'legacy_format' => $summary['legacy_format'],
        ]);

        $this->retention->registerRecord(
            tenantId: $tenantId,
            subjectType: User::class,
            subjectId: (string) $subject->id,
            classification: $decision->classification,
            retentionCategory: 'dsr_audit',
            sourceSystem: 'export',
            sourceReference: $requestId,
        );

        return new PrivacyRequestResult($requestId, 'export', 'completed', $summary);
    }

    public function delete(User $actor, User $subject, string $idempotencyKey): PrivacyRequestResult
    {
        $tenantId = $this->authorize($actor, $subject, null);

        if ($existing = $this->store->findRequestByKey(
            idempotencyKey: $idempotencyKey,
            tenantId: $tenantId,
            actorId: (string) $actor->getKey(),
            subjectType: User::class,
            subjectId: (string) $subject->getKey(),
            requestType: 'delete',
        )) {
            return $this->resultFromRow($existing);
        }

        $this->assertNoActiveHold($subject, $tenantId);

        $requestId = $this->store->beginRequest(
            requestType: 'delete',
            idempotencyKey: $idempotencyKey,
            tenantId: $tenantId,
            actorId: (string) $actor->getKey(),
            subjectType: User::class,
            subjectId: (string) $subject->getKey(),
        );

        $this->store->appendEvent($requestId, 'request_created', ['mode' => 'legacy_delete']);
        $this->legacyPrivacy->delete($subject);
        $summary = ['deleted' => true, 'integration_seams' => $this->seams->keys()];
        $this->store->completeRequest($requestId, $summary);
        $this->store->appendEvent($requestId, 'request_completed', $summary);

        return new PrivacyRequestResult($requestId, 'delete', 'completed', $summary);
    }

    public function anonymize(User $actor, User $subject, string $idempotencyKey): PrivacyRequestResult
    {
        $tenantId = $this->authorize($actor, $subject, null);

        if ($existing = $this->store->findRequestByKey(
            idempotencyKey: $idempotencyKey,
            tenantId: $tenantId,
            actorId: (string) $actor->getKey(),
            subjectType: User::class,
            subjectId: (string) $subject->getKey(),
            requestType: 'anonymize',
        )) {
            return $this->resultFromRow($existing);
        }

        $this->assertNoActiveHold($subject, $tenantId);

        $requestId = $this->store->beginRequest(
            requestType: 'anonymize',
            idempotencyKey: $idempotencyKey,
            tenantId: $tenantId,
            actorId: (string) $actor->getKey(),
            subjectType: User::class,
            subjectId: (string) $subject->getKey(),
        );

        DB::transaction(function () use ($subject): void {
            DB::table('sessions')->where('user_id', $subject->getKey())->delete();
            DB::table('personal_access_tokens')->where('tokenable_id', $subject->getKey())->delete();
            DB::table('push_subscriptions')->where('subscribable_id', $subject->getKey())->delete();

            $subject->forceFill([
                'name' => 'Anonymized User',
                'email' => 'deleted+'.sha1((string) $subject->getKey()).'@dovra.invalid',
                'phone' => null,
                'email_verified_at' => null,
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->saveQuietly();
        });

        $summary = [
            'anonymized' => true,
            'email' => $subject->email,
            'integration_seams' => $this->seams->keys(),
        ];

        $this->store->appendEvent($requestId, 'request_created', ['mode' => 'anonymize']);
        $this->store->completeRequest($requestId, $summary);
        $this->store->appendEvent($requestId, 'request_completed', $summary);

        return new PrivacyRequestResult($requestId, 'anonymize', 'completed', $summary);
    }

    public function hold(User $actor, User $subject, ?Tenant $tenant, string $reason, string $idempotencyKey): PrivacyRequestResult
    {
        $tenantId = $this->authorize($actor, $subject, $tenant);

        if ($existing = $this->store->findRequestByKey(
            idempotencyKey: $idempotencyKey,
            tenantId: $tenantId,
            actorId: (string) $actor->getKey(),
            subjectType: User::class,
            subjectId: (string) $subject->getKey(),
            requestType: 'hold',
        )) {
            return $this->resultFromRow($existing);
        }

        $requestId = $this->store->beginRequest(
            requestType: 'hold',
            idempotencyKey: $idempotencyKey,
            tenantId: $tenantId,
            actorId: (string) $actor->getKey(),
            subjectType: User::class,
            subjectId: (string) $subject->getKey(),
        );

        $holdId = $this->store->upsertLegalHold(
            tenantId: $tenantId,
            subjectType: User::class,
            subjectId: (string) $subject->getKey(),
            requestedBy: (string) $actor->getKey(),
            reason: $reason,
        );

        $summary = [
            'hold_id' => $holdId,
            'reason' => $reason,
            'status' => 'active',
            'integration_seams' => $this->seams->keys(),
        ];

        $this->store->appendEvent($requestId, 'request_created', ['mode' => 'hold']);
        $this->store->completeRequest($requestId, $summary);
        $this->store->appendEvent($requestId, 'request_completed', $summary);

        return new PrivacyRequestResult($requestId, 'hold', 'completed', $summary);
    }

    private function authorize(User $actor, User $subject, ?Tenant $tenant): ?string
    {
        $subjectTenantId = $subject->tenant_id !== null ? (string) $subject->tenant_id : null;
        $requestedTenantId = $tenant !== null ? (string) $tenant->getKey() : $subjectTenantId;

        if ($tenant !== null && $subjectTenantId !== $requestedTenantId) {
            throw new AuthorizationException('The tenant does not own the privacy subject.');
        }

        if ((string) $actor->getKey() === (string) $subject->getKey()) {
            return $requestedTenantId;
        }

        if ($actor->isSuperAdmin()) {
            return $requestedTenantId;
        }

        $effectiveTenant = $tenant;
        if ($effectiveTenant === null && $requestedTenantId !== null) {
            $effectiveTenant = Tenant::query()->find($requestedTenantId);
        }

        if ($effectiveTenant !== null && $actor->canAccessTenant($effectiveTenant) && (string) $subject->tenant_id === (string) $effectiveTenant->getKey()) {
            return $requestedTenantId;
        }

        throw new AuthorizationException('The actor is not authorized for this privacy request.');
    }

    private function assertNoActiveHold(User $subject, ?string $tenantId): void
    {
        if ($this->store->hasActiveHold(User::class, (string) $subject->getKey(), $tenantId)) {
            throw ValidationException::withMessages([
                'privacy' => 'An active legal hold prevents this disposition.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resultFromRow(array $row): PrivacyRequestResult
    {
        return new PrivacyRequestResult(
            requestId: (string) $row['id'],
            action: (string) $row['request_type'],
            status: (string) $row['status'],
            summary: $row['result_summary'] ?? [],
        );
    }
}
