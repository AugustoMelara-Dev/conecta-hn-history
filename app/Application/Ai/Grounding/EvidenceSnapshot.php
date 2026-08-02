<?php

declare(strict_types=1);

namespace App\Application\Ai\Grounding;

use App\Application\Ai\Security\EncryptedAiContent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class EvidenceSnapshot
{
    public function __construct(
        public string $evidenceId,
        public string $sourceType,
        public string $sourceId,
        public string $sourceVersion,
        public string $tenantId,
        public string $sourceTenantId,
        public string $visibility,
        public string $citationLabel,
        public CarbonImmutable $freshAt,
        public int $retrievalRank,
        public float $retrievalScore,
        public ?string $currency,
        public ?int $priceMinor,
        public ?string $availability,
        public EncryptedAiContent $excerpt,
    ) {
        if (! Str::isUuid($tenantId) || ! Str::isUuid($sourceTenantId)
            || preg_match('/^[a-z][a-z0-9_]{0,31}$/', $sourceType) !== 1
            || trim($sourceId) === '' || mb_strlen($sourceId) > 64
            || $evidenceId !== $sourceType.':'.$sourceId
            || preg_match('/^[a-f0-9]{64}$/', $sourceVersion) !== 1
            || ! in_array($visibility, ['public', 'private'], true)
            || trim($citationLabel) === '' || mb_strlen($citationLabel) > 160
            || $retrievalRank < 1 || ! is_finite($retrievalScore)
            || $retrievalScore < -1.0 || $retrievalScore > 1.0
            || ($currency !== null && preg_match('/^[A-Z]{3}$/', $currency) !== 1)
            || ($priceMinor !== null && $priceMinor < 0)
            || ($availability !== null && ! in_array($availability, [
                'available', 'backorder', 'unavailable', 'unknown',
            ], true))) {
            throw new InvalidArgumentException('AI evidence snapshot is invalid.');
        }
    }
}
