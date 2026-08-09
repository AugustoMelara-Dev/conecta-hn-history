<?php

declare(strict_types=1);

namespace App\Application\BusinessGraph;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class BusinessGraphFact
{
    /**
     * @param  array<string, mixed>  $value
     */
    private function __construct(
        public string $tenantId,
        public string $subjectType,
        public string $subjectId,
        public string $factKey,
        public array $value,
        public string $sourceType,
        public string $sourceId,
        public ?float $confidence,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validUntil,
        public ?DateTimeImmutable $revokedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromSource(
        string $tenantId,
        string $subjectType,
        string $subjectId,
        string $factKey,
        array $value,
        string $sourceType,
        string $sourceId,
        ?float $confidence = null,
    ): self {
        return new self(
            tenantId: self::requireNonEmpty($tenantId, 'tenantId'),
            subjectType: self::requireNonEmpty($subjectType, 'subjectType'),
            subjectId: self::requireNonEmpty($subjectId, 'subjectId'),
            factKey: self::requireNonEmpty($factKey, 'factKey'),
            value: $value,
            sourceType: self::requireNonEmpty($sourceType, 'sourceType'),
            sourceId: self::requireNonEmpty($sourceId, 'sourceId'),
            confidence: self::validateConfidence($confidence),
            validFrom: new DateTimeImmutable,
            validUntil: null,
            revokedAt: null,
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromPersistence(
        string $tenantId,
        string $subjectType,
        string $subjectId,
        string $factKey,
        array $value,
        string $sourceType,
        string $sourceId,
        ?float $confidence,
        DateTimeInterface|string $validFrom,
        DateTimeInterface|string|null $validUntil,
        DateTimeInterface|string|null $revokedAt,
    ): self {
        return new self(
            tenantId: self::requireNonEmpty($tenantId, 'tenantId'),
            subjectType: self::requireNonEmpty($subjectType, 'subjectType'),
            subjectId: self::requireNonEmpty($subjectId, 'subjectId'),
            factKey: self::requireNonEmpty($factKey, 'factKey'),
            value: $value,
            sourceType: self::requireNonEmpty($sourceType, 'sourceType'),
            sourceId: self::requireNonEmpty($sourceId, 'sourceId'),
            confidence: self::validateConfidence($confidence),
            validFrom: self::toDateTimeImmutable($validFrom),
            validUntil: self::toNullableDateTimeImmutable($validUntil),
            revokedAt: self::toNullableDateTimeImmutable($revokedAt),
        );
    }

    private static function requireNonEmpty(string $value, string $field): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException("BusinessGraphFact {$field} must not be empty.");
        }

        return $trimmed;
    }

    private static function validateConfidence(?float $confidence): ?float
    {
        if ($confidence !== null && ($confidence < 0.0 || $confidence > 1.0)) {
            throw new InvalidArgumentException('BusinessGraphFact confidence must be between 0 and 1.');
        }

        return $confidence;
    }

    private static function toNullableDateTimeImmutable(DateTimeInterface|string|null $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return self::toDateTimeImmutable($value);
    }

    private static function toDateTimeImmutable(DateTimeInterface|string $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable($value);
    }
}
