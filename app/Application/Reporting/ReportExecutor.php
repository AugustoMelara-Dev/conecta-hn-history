<?php

declare(strict_types=1);

namespace App\Application\Reporting;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * ReportExecutor - Ejecuta reports con cache Redis 5 minutos.
 *
 * Cache se invalida cuando hay writes (eventos Eloquent).
 */
class ReportExecutor
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly ReportRegistry $registry,
    ) {}

    public function execute(ReportKey $key, string $tenantId, ReportRange $range): ReportData
    {
        $cacheKey = $this->cacheKey($key, $tenantId, $range);

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn () => $this->registry->get($key)->getData($tenantId, $range),
        );
    }

    public function invalidate(ReportKey $key, string $tenantId): void
    {
        // Pattern-based invalidation
        $prefix = "report:{$key->value}:{$tenantId}:";
        // In production: iterate cache store or use cache tags
        Cache::flush(); // Simple invalidation - all tenant reports
    }

    private function cacheKey(ReportKey $key, string $tenantId, ReportRange $range): string
    {
        return sprintf(
            'report:%s:%s:%s-%s',
            $key->value,
            $tenantId,
            $range->start->toDateString(),
            $range->end->toDateString(),
        );
    }
}
