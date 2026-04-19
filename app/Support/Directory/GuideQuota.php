<?php

declare(strict_types=1);

namespace App\Support\Directory;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;

final class GuideQuota
{
    private const LIMITER_NAME = 'directory-guide-daily';

    public static function limit(): int
    {
        return max(1, (int) config('directory.guide.daily_quota.limit', 5));
    }

    public static function bucketKey(Request $request, ?Carbon $now = null): string
    {
        $date = self::dateKey($now);
        $scope = $request->user()?->getAuthIdentifier();
        $identity = $scope !== null ? 'user:'.$scope : 'ip:'.$request->ip();

        return 'directory-guide-daily:'.$identity.':'.$date;
    }

    public static function status(Request $request, ?Carbon $now = null): array
    {
        $limit = self::limit();
        $used = (int) RateLimiter::attempts(self::limiterKey($request, $now));
        $used = max(0, min($limit, $used));
        $remaining = max(0, $limit - $used);
        $resetAt = self::resetAt($now);

        return [
            'quota_limit' => $limit,
            'quota_used' => $used,
            'quota_remaining' => $remaining,
            'quota_reset_at' => $resetAt->toIso8601String(),
        ];
    }

    public static function retryAfterSeconds(Request $request, ?Carbon $now = null): int
    {
        $seconds = (int) RateLimiter::availableIn(self::limiterKey($request, $now));

        if ($seconds > 0) {
            return $seconds;
        }

        $reference = $now ?? now(self::timezone());
        $resetAt = self::resetAt($reference);

        return max(1, (int) ($resetAt->getTimestamp() - $reference->getTimestamp()));
    }

    private static function dateKey(?Carbon $now = null): string
    {
        $reference = $now ?? now(self::timezone());

        return $reference->toDateString();
    }

    private static function resetAt(?Carbon $now = null): Carbon
    {
        $reference = $now ?? now(self::timezone());

        return $reference->copy()->endOfDay()->addSecond();
    }

    private static function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    private static function limiterKey(Request $request, ?Carbon $now = null): string
    {
        return md5(self::LIMITER_NAME.self::bucketKey($request, $now));
    }
}
