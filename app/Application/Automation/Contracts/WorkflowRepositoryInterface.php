<?php

declare(strict_types=1);

namespace App\Application\Automation\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

interface WorkflowRepositoryInterface
{
    public function connection(): ConnectionInterface;

    /** @return array<string, mixed>|null */
    public function findToolPolicy(string $tenantId, string $tool, string $toolVersion): ?array;

    public function claimIdempotency(
        string $tenantId,
        string $scope,
        string $idempotencyKey,
        string $fingerprint,
        CarbonImmutable $occurredAt,
    ): bool;

    public function hasIdempotencyClaim(string $tenantId, string $scope, string $idempotencyKey): bool;

    /** @return array<string, mixed>|null */
    public function lockConfirmationAttempt(string $confirmationId): ?array;

    public function updateConfirmationAttempt(string $confirmationId, string $status, CarbonImmutable $occurredAt): void;

    /** @param  array<string, mixed>  $definition */
    public function createDraftVersion(
        string $tenantId,
        string $workflowKey,
        array $definition,
        string $actorId,
    ): array;

    public function publishDraftVersion(
        string $tenantId,
        string $workflowKey,
        string $draftVersionId,
        string $publishedVersion,
        string $actorId,
        CarbonImmutable $occurredAt,
    ): array;

    /** @param  array<string, mixed>  $definition */
    public function updateDraftVersion(
        string $tenantId,
        string $versionId,
        array $definition,
        CarbonImmutable $occurredAt,
    ): void;

    /** @return array<string, mixed>|null */
    public function findPublishedVersion(string $tenantId, string $workflowKey, string $version): ?array;

    /** @return array<string, mixed>|null */
    public function loadWorkflowVersion(string $workflowVersionId): ?array;

    /** @return array<string, mixed>|null */
    public function loadWorkflowVersionForTenant(string $workflowVersionId, string $tenantId): ?array;

    /** @return array<string, mixed>|null */
    public function findWorkflowRunByIdempotency(
        string $tenantId,
        string $workflowKey,
        string $workflowVersion,
        string $idempotencyKey,
    ): ?array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{run: array<string, mixed>, replayed: bool}
     */
    public function findOrCreateWorkflowRun(
        string $tenantId,
        string $workflowKey,
        string $workflowVersionId,
        string $workflowVersion,
        string $actorId,
        string $plan,
        string $channel,
        string $trigger,
        string $idempotencyKey,
        array $payload,
        CarbonImmutable $occurredAt,
    ): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createWorkflowRun(
        string $tenantId,
        string $workflowKey,
        string $workflowVersionId,
        string $workflowVersion,
        string $actorId,
        string $plan,
        string $channel,
        string $trigger,
        string $idempotencyKey,
        array $payload,
        CarbonImmutable $occurredAt,
    ): array;

    /** @return array<string, mixed>|null */
    public function loadWorkflowRun(string $runId): ?array;

    /** @return array<string, mixed>|null */
    public function loadWorkflowRunForTenant(string $runId, string $tenantId): ?array;

    /** @param  array<string, mixed>  $attributes */
    public function updateWorkflowRun(string $runId, array $attributes): void;

    /** @return array<string, mixed>|null */
    public function loadWorkflowStep(string $runId, string $stepKey): ?array;

    /** @param  array<string, mixed>  $attributes */
    public function saveWorkflowStep(string $runId, string $tenantId, string $stepKey, array $attributes): array;

    /** @return array<string, mixed> */
    public function createWorkflowStepAttempt(
        string $runId,
        string $tenantId,
        string $stepKey,
        int $attemptNumber,
        string $attemptKind,
        string $providerToolKey,
        string $idempotencyKey,
        CarbonImmutable $startedAt,
    ): array;

    /** @param  array<string, mixed>  $attributes */
    public function finishWorkflowStepAttempt(string $attemptId, array $attributes): void;

    /** @return list<array<string, mixed>> */
    public function listCompensableCompletedSteps(string $runId): array;

    public function hasWebhookDelivery(string $tenantId, string $provider, string $externalId): bool;

    public function recordWebhookDelivery(
        string $tenantId,
        string $actorId,
        string $provider,
        string $market,
        string $version,
        string $capability,
        string $externalId,
        string $payloadHash,
        CarbonImmutable $occurredAt,
    ): bool;
}
