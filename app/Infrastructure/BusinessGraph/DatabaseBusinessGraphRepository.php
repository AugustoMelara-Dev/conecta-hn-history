<?php

declare(strict_types=1);

namespace App\Infrastructure\BusinessGraph;

use App\Application\BusinessGraph\BusinessGraphEdge;
use App\Application\BusinessGraph\BusinessGraphFact;
use App\Application\BusinessGraph\BusinessGraphSnapshot;
use App\Application\BusinessGraph\Contracts\BusinessGraphRepositoryInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DatabaseBusinessGraphRepository implements BusinessGraphRepositoryInterface
{
    public function upsertFact(BusinessGraphFact $fact): void
    {
        $now = now();

        DB::table('dovra_business_facts')->upsert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $fact->tenantId,
                'subject_type' => $fact->subjectType,
                'subject_id' => $fact->subjectId,
                'fact_key' => $fact->factKey,
                'value' => $this->encodeJson($fact->value),
                'source_type' => $fact->sourceType,
                'source_id' => $fact->sourceId,
                'confidence' => $fact->confidence,
                'valid_from' => $this->formatDateTime($fact->validFrom),
                'valid_until' => $this->formatNullableDateTime($fact->validUntil),
                'revoked_at' => $this->formatNullableDateTime($fact->revokedAt),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], [
            'tenant_id',
            'subject_type',
            'subject_id',
            'fact_key',
            'source_type',
            'source_id',
        ], [
            'value',
            'confidence',
            'valid_from',
            'valid_until',
            'revoked_at',
            'updated_at',
        ]);
    }

    public function upsertEdge(BusinessGraphEdge $edge): void
    {
        $now = now();

        DB::table('dovra_business_edges')->upsert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $edge->tenantId,
                'from_type' => $edge->fromType,
                'from_id' => $edge->fromId,
                'relation' => $edge->relation,
                'to_type' => $edge->toType,
                'to_id' => $edge->toId,
                'source_type' => $edge->sourceType,
                'source_id' => $edge->sourceId,
                'valid_from' => $this->formatDateTime($edge->validFrom),
                'valid_until' => $this->formatNullableDateTime($edge->validUntil),
                'revoked_at' => $this->formatNullableDateTime($edge->revokedAt),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], [
            'tenant_id',
            'from_type',
            'from_id',
            'relation',
            'to_type',
            'to_id',
            'source_type',
            'source_id',
        ], [
            'valid_from',
            'valid_until',
            'revoked_at',
            'updated_at',
        ]);
    }

    public function snapshot(string $tenantId): BusinessGraphSnapshot
    {
        $tenantId = trim($tenantId);

        if ($tenantId === '') {
            throw new InvalidArgumentException('BusinessGraphRepository snapshot tenantId must not be empty.');
        }

        $now = now();

        $facts = DB::table('dovra_business_facts')
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->where('valid_from', '<=', $now)
            ->where(static function ($query) use ($now): void {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', $now);
            })
            ->orderBy('subject_type')
            ->orderBy('subject_id')
            ->orderBy('fact_key')
            ->orderBy('source_type')
            ->orderBy('source_id')
            ->get()
            ->map(fn (object $row): BusinessGraphFact => BusinessGraphFact::fromPersistence(
                tenantId: (string) $row->tenant_id,
                subjectType: (string) $row->subject_type,
                subjectId: (string) $row->subject_id,
                factKey: (string) $row->fact_key,
                value: $this->decodeJson($row->value),
                sourceType: (string) $row->source_type,
                sourceId: (string) $row->source_id,
                confidence: $row->confidence === null ? null : (float) $row->confidence,
                validFrom: (string) $row->valid_from,
                validUntil: $row->valid_until === null ? null : (string) $row->valid_until,
                revokedAt: $row->revoked_at === null ? null : (string) $row->revoked_at,
            ))
            ->values()
            ->all();

        $edges = DB::table('dovra_business_edges')
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->where('valid_from', '<=', $now)
            ->where(static function ($query) use ($now): void {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', $now);
            })
            ->orderBy('from_type')
            ->orderBy('from_id')
            ->orderBy('relation')
            ->orderBy('to_type')
            ->orderBy('to_id')
            ->orderBy('source_type')
            ->orderBy('source_id')
            ->get()
            ->map(fn (object $row): BusinessGraphEdge => BusinessGraphEdge::fromPersistence(
                tenantId: (string) $row->tenant_id,
                fromType: (string) $row->from_type,
                fromId: (string) $row->from_id,
                relation: (string) $row->relation,
                toType: (string) $row->to_type,
                toId: (string) $row->to_id,
                sourceType: (string) $row->source_type,
                sourceId: (string) $row->source_id,
                validFrom: (string) $row->valid_from,
                validUntil: $row->valid_until === null ? null : (string) $row->valid_until,
                revokedAt: $row->revoked_at === null ? null : (string) $row->revoked_at,
            ))
            ->values()
            ->all();

        return new BusinessGraphSnapshot($tenantId, $facts, $edges);
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Business graph JSON value must decode to an array.');
        }

        return $decoded;
    }

    private function formatNullableDateTime(?DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->formatDateTime($value);
    }

    private function formatDateTime(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s');
    }
}
