import axios, { AxiosRequestConfig } from 'axios';

interface CacheItem<T> {
    data: T;
    expiry: number;
    url: string;
}

export class TitaniumHttpAdapter {
    // Core data structures
    private cache = new Map<string, CacheItem<unknown>>();

    private inflight = new Map<string, Promise<unknown>>();

    private readonly MAX_CACHE_SIZE = 50;

    public async execute<T>(
        config: AxiosRequestConfig,
        meta: { ttl?: number; sla?: number } = {}
    ): Promise<T> {
        const method = config.method?.toUpperCase() || 'GET';

        // Mutations sweep related cache entries for the same resource.
        if (method !== 'GET') {
            const baseUrl = config.url?.split('?')[0] || '';

            for (const key of this.cache.keys()) {
                if (key.includes(baseUrl)) {
                    this.cache.delete(key);
                    if (import.meta.env.DEV) {
                        console.debug(`[Titanium Bust] Invalidated via sweep: ${key}`);
                    }
                }
            }

            return this.performNetworkRequest<T>(config, meta, '', false);
        }

        const cacheKey = this.generateCacheKey(config);
        const cachedData = this.getCache<T>(cacheKey);

        if (cachedData) {
            if (Date.now() < cachedData.expiry) {
                if (import.meta.env.DEV) {
                    console.debug(`[Titanium LRU] Hit: ${cacheKey}`);
                }
                return Promise.resolve(cachedData.data);
            }

            this.cache.delete(cacheKey);
        }

        // Deduplicate concurrent reads for the same key.
        if (this.inflight.has(cacheKey)) {
            if (import.meta.env.DEV) {
                console.debug(`[Titanium Sync] Deduplication: ${cacheKey}`);
            }
            return this.inflight.get(cacheKey) as Promise<T>;
        }

        const requestPromise = this.performNetworkRequest<T>(config, meta, cacheKey, true)
            .finally(() => {
                this.inflight.delete(cacheKey);
            });

        this.inflight.set(cacheKey, requestPromise);

        return requestPromise;
    }

    private async performNetworkRequest<T>(
        config: AxiosRequestConfig,
        meta: { ttl?: number; sla?: number },
        cacheKey: string,
        shouldCache: boolean
    ): Promise<T> {
        const start = performance.now();
        const response = await axios.request<T>(config);
        const duration = performance.now() - start;

        if (meta.sla && duration > meta.sla) {
            this.reportSlowRequest(config.url || 'unknown', duration, meta.sla);
        }

        if (shouldCache && meta.ttl) {
            this.setCache(cacheKey, response.data, meta.ttl, config.url || '');
        }

        return response.data;
    }

    // LRU cache management
    private getCache<T>(key: string): CacheItem<T> | undefined {
        if (!this.cache.has(key)) return undefined;

        const item = this.cache.get(key)!;
        this.cache.delete(key);
        this.cache.set(key, item);

        return item as CacheItem<T>;
    }

    private setCache(key: string, data: unknown, ttlSeconds: number, url: string): void {
        if (this.cache.size >= this.MAX_CACHE_SIZE) {
            const oldestKey = this.cache.keys().next().value;
            if (oldestKey) {
                this.cache.delete(oldestKey);
            }
        }

        this.cache.delete(key);

        this.cache.set(key, {
            data,
            expiry: Date.now() + (ttlSeconds * 1000),
            url: url || '',
        });
    }

    // Generate a canonical cache key using sorted params.
    private generateCacheKey(config: AxiosRequestConfig): string {
        const method = config.method?.toUpperCase() || 'GET';
        const url = config.url || '';
        const params = config.params || {};
        const canonicalParams = TitaniumHttpAdapter.canonicalize(params);

        return `${method}::${url}::${JSON.stringify(canonicalParams)}`;
    }

    // Recursively normalize params before stringifying them.
    private static canonicalize(obj: unknown): unknown {
        if (obj === null || typeof obj !== 'object') {
            return obj;
        }

        if (Array.isArray(obj)) {
            return obj.map(item => TitaniumHttpAdapter.canonicalize(item));
        }

        const sortedKeys = Object.keys(obj).sort();
        const result: Record<string, unknown> = {};

        for (const key of sortedKeys) {
            result[key] = TitaniumHttpAdapter.canonicalize((obj as Record<string, unknown>)[key]);
        }

        return result;
    }

    private reportSlowRequest(url: string, duration: number, threshold: number): void {
        const msg = `[Titanium SLA Alert] Slow request detected: ${url} took ${duration.toFixed(2)}ms (threshold: ${threshold}ms)`;
        console.warn(msg);
    }
}
