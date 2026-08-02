<?php

declare(strict_types=1);

namespace App\Application\BusinessGraph;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class BusinessGraphEdge
{
    private function __construct(
        public string $tenantId,
        public string $fromType,
        public string $fromId,
        public string $relation,
        public string $toType,
        public string $toId,
        public string $sourceType,
        public string $sourceId,
        public DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validUntil,
        public ?DateTimeImmutable $revokedAt,
    ) {}

    public static function fromSource(
        string $tenantId,
        string $fromType,
        string $fromId,
        string $relation,
        string $toType,
        string $toId,
        string $sourceType,
        string $sourceId,
    ): self {
        return new self(
            tenantId: self::requireNonEmpty($tenantId, 'tenantId'),
            fromType: self::requireNonEmpty($fromType, 'fromType'),
            fromId: self::requireNonEmpty($fromId, 'fromId'),
            relation: self::requireNonEmpty($relation, 'relation'),
            toType: self::requireNonEmpty($toType, 'toType'),
            toId: self::requireNonEmpty($toId, 'toId'),
            sourceType: self::requireNonEmpty($sourceType, 'sourceType'),
            sourceId: self::requireNonEmpty($sourceId, 'sourceId'),
            validFrom: new DateTimeImmutable,
            validUntil: null,
            revokedAt: null,
        );
    }

    public static function fromPersistence(
        string $tenantId,
        string $fromType,
        string $fromId,
        string $relation,
        string $toType,
        string $toId,
        string $sourceType,
        string $sourceId,
        DateTimeInterface|string $validFrom,
        DateTimeInterface|string|null $validUntil,
        DateTimeInterface|string|null $revokedAt,
    ): self {
        return new self(
            tenantId: self::requireNonEmpty($tenantId, 'tenantId'),
            fromType: self::requireNonEmpty($fromType, 'fromType'),
            fromId: self::requireNonEmpty($fromId, 'fromId'),
            relation: self::requireNonEmpty($relation, 'relation'),
            toType: self::requireNonEmpty($toType, 'toType'),
            toId: self::requireNonEmpty($toId, 'toId'),
            sourceType: self::requireNonEmpty($sourceType, 'sourceType'),
            sourceId: self::requireNonEmpty($sourceId, 'sourceId'),
            validFrom: self::toDateTimeImmutable($validFrom),
            validUntil: self::toNullableDateTimeImmutable($validUntil),
            revokedAt: self::toNullableDateTimeImmutable($revokedAt),
        );
    }

    private static function requireNonEmpty(string $value, string $field): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidArgumentException("BusinessGraphEdge {$field} must not be empty.");
        }

        return $trimmed;
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
