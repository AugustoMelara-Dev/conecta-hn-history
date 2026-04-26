<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicCompiledCacheInterface;
use Illuminate\Support\Facades\Cache;

final class DynamicCompiledCacheService implements DynamicCompiledCacheInterface
{
    /**
     * @param  \Closure():array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public function rememberSchema(?string $tenantId, string $entityKey, int $schemaVersion, \Closure $resolver): array
    {
        $key = $this->schemaCacheKey($tenantId, $entityKey, $schemaVersion);
        $ttl = (int) config('dynamic-forms.compiled_cache.ttl_seconds', 900);

        /** @var array<string, mixed> $payload */
        $payload = Cache::remember($key, $ttl, $resolver);
        $this->trackKey($this->schemaIndexKey($tenantId, $entityKey), $key);
        $this->trackKey($this->schemaTenantIndexKey($tenantId), $key);

        return $payload;
    }

    /**
     * @param  \Closure():array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public function rememberTemplate(?string $tenantId, string $templateKey, int $templateVersion, \Closure $resolver): array
    {
        $key = $this->templateCacheKey($tenantId, $templateKey, $templateVersion);
        $ttl = (int) config('dynamic-forms.compiled_cache.ttl_seconds', 900);

        /** @var array<string, mixed> $payload */
        $payload = Cache::remember($key, $ttl, $resolver);
        $this->trackKey($this->templateIndexKey($tenantId, $templateKey), $key);
        $this->trackKey($this->templateTenantIndexKey($tenantId), $key);

        return $payload;
    }

    public function forgetSchema(?string $tenantId, string $entityKey, ?int $schemaVersion = null): void
    {
        if ($schemaVersion !== null) {
            Cache::forget($this->schemaCacheKey($tenantId, $entityKey, $schemaVersion));

            return;
        }

        $indexKey = $this->schemaIndexKey($tenantId, $entityKey);
        $keys = $this->getTrackedKeys($indexKey);
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget($indexKey);
    }

    public function forgetTemplate(?string $tenantId, string $templateKey, ?int $templateVersion = null): void
    {
        if ($templateVersion !== null) {
            Cache::forget($this->templateCacheKey($tenantId, $templateKey, $templateVersion));

            return;
        }

        $indexKey = $this->templateIndexKey($tenantId, $templateKey);
        $keys = $this->getTrackedKeys($indexKey);
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget($indexKey);
    }

    public function flushTenant(?string $tenantId): void
    {
        $schemaTenantIndex = $this->schemaTenantIndexKey($tenantId);
        foreach ($this->getTrackedKeys($schemaTenantIndex) as $key) {
            Cache::forget($key);
        }
        Cache::forget($schemaTenantIndex);

        $templateTenantIndex = $this->templateTenantIndexKey($tenantId);
        foreach ($this->getTrackedKeys($templateTenantIndex) as $key) {
            Cache::forget($key);
        }
        Cache::forget($templateTenantIndex);
    }

    public function schemaCacheKey(?string $tenantId, string $entityKey, int $schemaVersion): string
    {
        $tenantSegment = $this->tenantSegment($tenantId);

        return "dyn:schema:{$tenantSegment}:{$entityKey}:{$schemaVersion}";
    }

    public function templateCacheKey(?string $tenantId, string $templateKey, int $templateVersion): string
    {
        $tenantSegment = $this->tenantSegment($tenantId);

        return "dyn:template:{$tenantSegment}:{$templateKey}:{$templateVersion}";
    }

    private function schemaIndexKey(?string $tenantId, string $entityKey): string
    {
        return "dyn:schema:index:".$this->tenantSegment($tenantId).":{$entityKey}";
    }

    private function templateIndexKey(?string $tenantId, string $templateKey): string
    {
        return "dyn:template:index:".$this->tenantSegment($tenantId).":{$templateKey}";
    }

    private function schemaTenantIndexKey(?string $tenantId): string
    {
        return "dyn:schema:tenant-index:".$this->tenantSegment($tenantId);
    }

    private function templateTenantIndexKey(?string $tenantId): string
    {
        return "dyn:template:tenant-index:".$this->tenantSegment($tenantId);
    }

    private function tenantSegment(?string $tenantId): string
    {
        $tenant = trim((string) $tenantId);

        return $tenant !== '' ? $tenant : 'global';
    }

    /**
     * @return list<string>
     */
    private function getTrackedKeys(string $indexKey): array
    {
        $keys = Cache::get($indexKey, []);
        if (! is_array($keys)) {
            return [];
        }

        $normalized = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $keys),
            static fn (string $value): bool => $value !== ''
        )));

        return $normalized;
    }

    private function trackKey(string $indexKey, string $value): void
    {
        $keys = $this->getTrackedKeys($indexKey);
        if (! in_array($value, $keys, true)) {
            $keys[] = $value;
        }

        $ttl = (int) config('dynamic-forms.compiled_cache.ttl_seconds', 900);
        Cache::put($indexKey, array_values($keys), $ttl);
    }
}

