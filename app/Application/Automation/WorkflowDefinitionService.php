<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Application\Automation\Contracts\WorkflowRepositoryInterface;
use Carbon\CarbonImmutable;

final readonly class WorkflowDefinitionService
{
    public function __construct(
        private WorkflowRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function createDraft(
        string $tenantId,
        string $workflowKey,
        array $definition,
        string $actorId,
    ): array {
        return $this->repository->createDraftVersion($tenantId, $workflowKey, $definition, $actorId);
    }

    /**
     * @return array<string, mixed>
     */
    public function publishDraft(
        string $tenantId,
        string $workflowKey,
        string $draftVersionId,
        string $publishedVersion,
        string $actorId,
    ): array {
        return $this->repository->publishDraftVersion(
            $tenantId,
            $workflowKey,
            $draftVersionId,
            $publishedVersion,
            $actorId,
            CarbonImmutable::now(),
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function updateDraftDefinition(string $tenantId, string $versionId, array $definition): void
    {
        $this->repository->updateDraftVersion($tenantId, $versionId, $definition, CarbonImmutable::now());
    }

    /** @return array<string, mixed>|null */
    public function publishedVersion(string $tenantId, string $workflowKey, string $version): ?array
    {
        return $this->repository->findPublishedVersion($tenantId, $workflowKey, $version);
    }
}
