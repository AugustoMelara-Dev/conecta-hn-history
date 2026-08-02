<?php

declare(strict_types=1);

namespace App\Infrastructure\Privacy\Dovra;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabasePrivacyEvidenceStore
{
    public function beginRequest(
        string $requestType,
        string $idempotencyKey,
        ?string $tenantId,
        ?string $actorId,
        string $subjectType,
        string $subjectId,
    ): string {
        $id = (string) Str::uuid();

        DB::table('dovra_privacy_requests')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'request_type' => $requestType,
            'idempotency_key' => $idempotencyKey,
            'status' => 'pending',
            'requested_at' => now()->utc(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRequestByKey(
        string $idempotencyKey,
        ?string $tenantId,
        ?string $actorId,
        string $subjectType,
        string $subjectId,
        string $requestType,
    ): ?array {
        $row = DB::table('dovra_privacy_requests')
            ->where('idempotency_key', $idempotencyKey)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('request_type', $requestType)
            ->when($tenantId === null, static fn ($query) => $query->whereNull('tenant_id'))
            ->when($tenantId !== null, static fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($actorId === null, static fn ($query) => $query->whereNull('actor_id'))
            ->when($actorId !== null, static fn ($query) => $query->where('actor_id', $actorId))
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'request_type' => (string) $row->request_type,
            'status' => (string) $row->status,
            'result_summary' => $this->decodeJson($row->result_summary),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function completeRequest(string $requestId, array $summary): void
    {
        DB::table('dovra_privacy_requests')
            ->where('id', $requestId)
            ->update([
                'status' => 'completed',
                'completed_at' => now()->utc(),
                'result_summary' => json_encode($summary, JSON_THROW_ON_ERROR),
                'updated_at' => now()->utc(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function appendEvent(string $requestId, string $eventType, array $context): void
    {
        $request = DB::table('dovra_privacy_requests')->where('id', $requestId)->first(['tenant_id', 'actor_id']);

        DB::table('dovra_privacy_request_events')->insert([
            'id' => (string) Str::uuid(),
            'privacy_request_id' => $requestId,
            'tenant_id' => $request?->tenant_id,
            'actor_id' => $request?->actor_id,
            'event_type' => $eventType,
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'occurred_at' => now()->utc(),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);
    }

    public function upsertLegalHold(
        ?string $tenantId,
        string $subjectType,
        string $subjectId,
        ?string $requestedBy,
        string $reason,
    ): string {
        $existingQuery = DB::table('dovra_legal_holds')
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', 'active');

        $this->scopeNullableTenant($existingQuery, $tenantId);
        $existing = $existingQuery->first(['id']);

        if ($existing !== null) {
            return (string) $existing->id;
        }

        $id = (string) Str::uuid();
        DB::table('dovra_legal_holds')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'requested_by' => $requestedBy,
            'reason' => $reason,
            'status' => 'active',
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $id;
    }

    public function hasActiveHold(string $subjectType, string $subjectId, ?string $tenantId): bool
    {
        $query = DB::table('dovra_legal_holds')
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', 'active');

        $this->scopeNullableTenant($query, $tenantId);

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function registerRetentionRecord(
        ?string $tenantId,
        string $subjectType,
        string $subjectId,
        string $classification,
        string $retentionCategory,
        string $sourceSystem,
        string $sourceReference,
        \DateTimeInterface $retainUntil,
        \DateTimeInterface $purgeAfter,
        array $metadata = [],
    ): string {
        $id = (string) Str::uuid();

        DB::table('dovra_data_retention_records')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'classification' => $classification,
            'retention_category' => $retentionCategory,
            'source_system' => $sourceSystem,
            'source_reference' => $sourceReference,
            'retain_until' => $retainUntil,
            'purge_after' => $purgeAfter,
            'disposition_status' => 'pending',
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now()->utc(),
            'updated_at' => now()->utc(),
        ]);

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expiredRetentionRecords(): array
    {
        return DB::table('dovra_data_retention_records')
            ->where('disposition_status', 'pending')
            ->where('purge_after', '<=', now()->utc())
            ->orderBy('purge_after')
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    public function markRetentionDisposed(string $recordId): void
    {
        DB::table('dovra_data_retention_records')
            ->where('id', $recordId)
            ->update([
                'disposition_status' => 'disposed',
                'last_disposition_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);
    }

    private function decodeJson(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function scopeNullableTenant(mixed $query, ?string $tenantId): void
    {
        if ($tenantId === null) {
            $query->whereNull('tenant_id');

            return;
        }

        $query->where('tenant_id', $tenantId);
    }
}
