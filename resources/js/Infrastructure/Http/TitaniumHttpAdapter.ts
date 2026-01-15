import axios, { AxiosRequestConfig } from 'axios';

interface CacheItem<T> {
    data: T;
    expiry: number;
    url: string;
}

export class TitaniumHttpAdapter {
    // 🧠 A. ESTRUCTURAS DE DATOS AVANZADAS

    // Almacenamiento LRU (Map mantiene orden de inserción)
    private cache = new Map<string, CacheItem<any>>();

    // Registro de Promesas en Vuelo (Deduplicación)
    private inflight = new Map<string, Promise<any>>();

    // Ley de Memoria Finita
    private readonly MAX_CACHE_SIZE = 50;

    public async execute<T>(
        config: AxiosRequestConfig,
        meta: { ttl?: number; sla?: number } = {}
    ): Promise<T> {
        const method = config.method?.toUpperCase() || 'GET';

        // 💥 ARTEFACTO 3: PROTOCOLO DE INVALIDACIÓN DE ÁREA (Scan & Destroy)
        if (method !== 'GET') {
            const baseUrl = config.url?.split('?')[0] || '';

            // Barrido de invalidación
            // Eliminamos CUALQUIER clave que contenga la URL del recurso mutado
            for (const key of this.cache.keys()) {
                if (key.includes(baseUrl)) {
                    this.cache.delete(key);
                    if (import.meta.env.DEV) {
                        console.debug(`[Titanium Bust] Invalidated via Sweep: ${key}`);
                    }
                }
            }

            // Las escrituras van directo a la red (sin deduplicación)
            return this.performNetworkRequest<T>(config, meta, '', false);
        }

        // � ARTEFACTO 2: GENERACIÓN DE IDENTIDAD CANÓNICA
        const cacheKey = this.generateCacheKey(config);

        // �🛡️ Verificación de Caché (Read Operations)
        const cachedData = this.getCache<T>(cacheKey);

        if (cachedData) {
            if (Date.now() < cachedData.expiry) {
                if (import.meta.env.DEV) {
                    console.debug(`[Titanium LRU] Hit: ${cacheKey}`);
                }
                return Promise.resolve(cachedData.data);
            } else {
                this.cache.delete(cacheKey);
            }
        }

        // 🛡️ GESTIÓN DE CONCURRENCIA (Deduplicación)
        if (this.inflight.has(cacheKey)) {
            if (import.meta.env.DEV) {
                console.debug(`[Titanium Sync] Deduplication: ${cacheKey}`);
            }
            return this.inflight.get(cacheKey) as Promise<T>;
        }

        // 🛡️ Ejecución de Red
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

        try {
            const response = await axios.request<T>(config);
            const duration = performance.now() - start;

            // SLA Monitoring
            if (meta.sla && duration > meta.sla) {
                this.reportSlowRequest(config.url || 'unknown', duration, meta.sla);
            }

            if (shouldCache && meta.ttl) {
                this.setCache(cacheKey, response.data, meta.ttl, config.url || '');
            }

            return response.data;
        } catch (error) {
            throw error;
        }
    }

    // 🔄 B. ALGORITMO DE GESTIÓN LRU

    private getCache<T>(key: string): CacheItem<T> | undefined {
        if (!this.cache.has(key)) return undefined;

        const item = this.cache.get(key)!;
        // Promote to newest
        this.cache.delete(key);
        this.cache.set(key, item);

        return item as CacheItem<T>;
    }

    private setCache(key: string, data: any, ttlSeconds: number, url: string): void {
        if (this.cache.size >= this.MAX_CACHE_SIZE) {
            const oldestKey = this.cache.keys().next().value;
            if (oldestKey) this.cache.delete(oldestKey);
        }

        // Ensure refresh if exists
        this.cache.delete(key);

        this.cache.set(key, {
            data,
            expiry: Date.now() + (ttlSeconds * 1000),
            url: url || '' // Store for invalidation matching
        });
    }

    // 🔑 GENERACIÓN DE CLAVE CANÓNICA (The Sort-First Rule)
    private generateCacheKey(config: AxiosRequestConfig): string {
        const method = config.method?.toUpperCase() || 'GET';
        const url = config.url || '';
        const params = config.params || {};

        // Ley de la Clave Canónica: Ordenar recursivamente
        const canonicalParams = TitaniumHttpAdapter.canonicalize(params);

        // Formato: METHOD::URL::CANONICAL_PARAMS
        // Ley de Exclusión de Payload: NO se incluye config.data
        return `${method}::${url}::${JSON.stringify(canonicalParams)}`;
    }

    // 📂 ARTEFACTO 1: EL MOTOR DE NORMALIZACIÓN RECURSIVA
    private static canonicalize(obj: any): any {
        // 1. Primitivos y Nulos
        if (obj === null || typeof obj !== 'object') {
            return obj;
        }

        // 2. Arrays (Recursividad en elementos)
        if (Array.isArray(obj)) {
            return obj.map(item => TitaniumHttpAdapter.canonicalize(item));
        }

        // 3. Objetos (Ordenamiento de llaves)
        const sortedKeys = Object.keys(obj).sort();
        const result: any = {};

        for (const key of sortedKeys) {
            result[key] = TitaniumHttpAdapter.canonicalize(obj[key]);
        }

        return result;
    }

    private reportSlowRequest(url: string, duration: number, threshold: number): void {
        const msg = `[Titanium SLA Alert] Slow Request Detected: ${url} took ${duration.toFixed(2)}ms (Threshold: ${threshold}ms)`;
        console.warn(msg);
    }
}
