<?php

declare(strict_types=1);

namespace App\Application\Privacy\Dovra;

use App\Infrastructure\Privacy\Dovra\DatabasePrivacyEvidenceStore;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class RetentionLifecycleService
{
    /**
     * @var array<string, array{retain:int,purge:int}>
     */
    private const WINDOWS = [
        'conversation_standard' => ['retain' => 90, 'purge' => 90],
        'dsr_audit' => ['retain' => 365, 'purge' => 365],
        'security_incident' => ['retain' => 365, 'purge' => 730],
        'prohibited_quarantine' => ['retain' => 1, 'purge' => 30],
    ];

    public function __construct(
        private readonly DatabasePrivacyEvidenceStore $store,
    ) {}

    public function registerRecord(
        ?string $tenantId,
        string $subjectType,
        string $subjectId,
        string $classification,
        string $retentionCategory,
        string $sourceSystem,
        string $sourceReference,
        ?\DateTimeInterface $createdAt = null,
        array $metadata = [],
    ): string {
        if (! array_key_exists($retentionCategory, self::WINDOWS)) {
            throw new InvalidArgumentException('Unknown retention category.');
        }

        $created = $createdAt === null
            ? now()->utc()
            : CarbonImmutable::instance($createdAt);
        $window = self::WINDOWS[$retentionCategory];

        return $this->store->registerRetentionRecord(
            tenantId: $tenantId,
            subjectType: $subjectType,
            subjectId: $subjectId,
            classification: $classification,
            retentionCategory: $retentionCategory,
            sourceSystem: $sourceSystem,
            sourceReference: $sourceReference,
            retainUntil: $created->addDays($window['retain']),
            purgeAfter: $created->addDays($window['purge']),
            metadata: $metadata,
        );
    }

    /**
     * @return array{disposed:int,held:int}
     */
    public function disposeExpired(): array
    {
        $disposed = 0;
        $held = 0;

        foreach ($this->store->expiredRetentionRecords() as $record) {
            if ($this->store->hasActiveHold((string) $record['subject_type'], (string) $record['subject_id'], $record['tenant_id'])) {
                $held++;

                continue;
            }

            $this->store->markRetentionDisposed((string) $record['id']);
            $disposed++;
        }

        return [
            'disposed' => $disposed,
            'held' => $held,
        ];
    }
}
