<?php

declare(strict_types=1);

namespace App\Support\Directory;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class GuideRequestTelemetry
{
    private const TABLE = 'directory_guide_request_metrics';

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $quotaStatus
     */
    public static function recordGuideResponse(
        Request $request,
        string $requestId,
        int $statusCode,
        array $meta,
        int $latencyMs,
        int $candidateCount,
        int $tenantCount,
        array $quotaStatus = [],
    ): void {
        self::insert([
            'request_id' => $requestId,
            'user_id' => self::resolveUserId($request),
            'status_code' => $statusCode,
            'ranking_mode' => self::nullableString($meta['ranking_mode'] ?? null, 32),
            'reasoning_basis' => self::nullableString($meta['reasoning_basis'] ?? null, 64),
            'degraded_reason' => self::nullableString($meta['degraded_reason'] ?? null, 64),
            'degraded_to_search' => (bool) ($meta['degraded_to_search'] ?? false),
            'candidate_count' => max(0, $candidateCount),
            'tenant_count' => max(0, $tenantCount),
            'latency_ms' => max(0, $latencyMs),
            'quota_limit' => self::nullableInt($quotaStatus['quota_limit'] ?? null),
            'quota_used' => self::nullableInt($quotaStatus['quota_used'] ?? null),
            'quota_remaining' => self::nullableInt($quotaStatus['quota_remaining'] ?? null),
            'quota_reset_at' => self::nullableTimestamp($quotaStatus['quota_reset_at'] ?? null),
            'budget' => self::nullableDecimal($request->query('budget')),
            'city' => self::nullableString($request->query('city'), 120),
            'category' => self::nullableString($request->query('category'), 120),
            'search' => self::nullableString($request->query('search'), 120),
        ]);
    }

    /**
     * @param  array<string, mixed>  $quotaStatus
     */
    public static function recordQuotaExceeded(
        Request $request,
        string $requestId,
        array $quotaStatus,
        int $retryAfterSeconds,
    ): void {
        self::insert([
            'request_id' => $requestId,
            'user_id' => self::resolveUserId($request),
            'status_code' => 429,
            'ranking_mode' => null,
            'reasoning_basis' => 'rate_limiter_directory_guide_daily',
            'degraded_reason' => 'quota_exceeded',
            'degraded_to_search' => true,
            'candidate_count' => 0,
            'tenant_count' => 0,
            'latency_ms' => max(0, $retryAfterSeconds * 1000),
            'quota_limit' => self::nullableInt($quotaStatus['quota_limit'] ?? null),
            'quota_used' => self::nullableInt($quotaStatus['quota_used'] ?? null),
            'quota_remaining' => self::nullableInt($quotaStatus['quota_remaining'] ?? 0),
            'quota_reset_at' => self::nullableTimestamp($quotaStatus['quota_reset_at'] ?? null),
            'budget' => self::nullableDecimal($request->query('budget')),
            'city' => self::nullableString($request->query('city'), 120),
            'category' => self::nullableString($request->query('category'), 120),
            'search' => self::nullableString($request->query('search'), 120),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function insert(array $payload): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        try {
            DB::table(self::TABLE)->insert(array_merge($payload, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (\Throwable $exception) {
            Log::warning('guide.telemetry.persist_failed', [
                'table' => self::TABLE,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private static function resolveUserId(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_string($id) && $id !== '' ? $id : null;
    }

    private static function nullableString(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $maxLength);
    }

    private static function nullableInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private static function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private static function nullableTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
