import { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Filter, MapPin, Search, Sparkles, X } from 'lucide-react';
import PublicLayout from '@/Layouts/PublicLayout';
import { PUBLIC_DISPLAY_FONT } from '@/lib/publicTypography';
import {
    getTenantInitials,
    resolveTenantCategoryName,
    resolveTenantCityName,
    resolveTenantDescription,
    resolveTenantPlanLabel,
    resolveTenantVisual,
} from '@/lib/tenantPresentation';

function cleanParams(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );
}

function DirectoryCard({ tenant }) {
    const visual = resolveTenantVisual(tenant);

    return (
        <Link
            href={route('tenant.microsite.show', { tenant: tenant.slug })}
            className="group flex h-full flex-col overflow-hidden rounded-[32px] border border-stone-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-slate-950/8"
        >
            <div className="relative aspect-[4/3] overflow-hidden bg-stone-100">
                {visual ? (
                    <img src={visual} alt={tenant.name} className="h-full w-full object-cover transition duration-700 group-hover:scale-105" />
                ) : (
                    <div className="flex h-full items-center justify-center bg-slate-950 text-3xl font-semibold text-white">
                        {getTenantInitials(tenant)}
                    </div>
                )}
                <div className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-slate-950/65 to-transparent" />
                <div className="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full border border-white/60 bg-white/90 px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">
                    <Sparkles className="h-3.5 w-3.5 text-slate-950" />
                    {resolveTenantPlanLabel(tenant)}
                </div>
            </div>

            <div className="flex flex-1 flex-col gap-4 p-5 sm:p-6">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full bg-[#f3f3f1] px-3 py-1 text-xs font-semibold text-slate-700">
                        {resolveTenantCategoryName(tenant)}
                    </span>
                    <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-slate-600">
                        {resolveTenantCityName(tenant)}
                    </span>
                </div>

                <div>
                    <h3 className="text-xl font-semibold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                        {tenant.name}
                    </h3>
                    <p className="mt-3 text-sm leading-7 text-slate-600">
                        {resolveTenantDescription(
                            tenant,
                            'Negocio local con presencia activa dentro de Conecta HN y una salida clara hacia contacto, pedido o reserva.',
                        )}
                    </p>
                </div>

                <div className="mt-auto flex items-center justify-between gap-3 border-t border-stone-100 pt-4">
                    <span className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <MapPin className="h-4 w-4 text-slate-950" />
                        {resolveTenantCityName(tenant)}
                    </span>
                    <span className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-[#f3f3f1] text-slate-700 transition group-hover:bg-slate-950 group-hover:text-white">
                        <ArrowRight className="h-4 w-4" />
                    </span>
                </div>
            </div>
        </Link>
    );
}

function EmptyState({ filters, onClear }) {
    return (
        <div className="rounded-[34px] border border-dashed border-stone-300 bg-white px-8 py-16 text-center shadow-sm">
            <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-[#f3f3f1]">
                <Search className="h-8 w-8 text-slate-400" />
            </div>
            <h3 className="mt-6 text-2xl font-semibold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                {filters.search ? `No encontramos "${filters.search}"` : 'No hay resultados con esos filtros'}
            </h3>
            <p className="mx-auto mt-4 max-w-2xl text-sm leading-7 text-slate-600">
                Ajusta ciudad, categoria o termino de busqueda. La ruta sigue siendo `/directorio`, pero esta ya es la vista completa de Negocios para filtrar y comparar.
            </p>
            <div className="mt-8 flex flex-col justify-center gap-4 sm:flex-row">
                <button
                    type="button"
                    onClick={onClear}
                    className="inline-flex items-center justify-center gap-2 rounded-full border border-stone-300 px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-stone-400 hover:bg-stone-50"
                >
                    <X className="h-4 w-4" />
                    Limpiar filtros
                </button>
                <Link
                    href={route('tenant.register')}
                    className="inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                    Registrar mi negocio
                    <ArrowRight className="h-4 w-4" />
                </Link>
            </div>
        </div>
    );
}

function formatQuotaResetAt(resetAt) {
    if (!resetAt) {
        return 'manana';
    }

    const parsed = new Date(resetAt);
    if (Number.isNaN(parsed.getTime())) {
        return 'manana';
    }

    return parsed.toLocaleString('es-HN', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
    });
}

function resolveGuideTenant(entry) {
    if (entry && typeof entry === 'object' && entry.tenant) {
        return entry.tenant;
    }

    return entry;
}

function DirectoryGuidePanel({ authUser, filters, searchTerm, cityTerm }) {
    const [budget, setBudget] = useState('100');
    const [chatPrompt, setChatPrompt] = useState('Que puedo comprar con 100 lps cerca de mi?');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isChatting, setIsChatting] = useState(false);
    const [guideResults, setGuideResults] = useState([]);
    const [guideMeta, setGuideMeta] = useState(null);
    const [guideError, setGuideError] = useState(null);
    const [chatAnswer, setChatAnswer] = useState(null);
    const [chatError, setChatError] = useState(null);
    const [chatMeta, setChatMeta] = useState(null);

    const isCustomer = Boolean(authUser?.persona?.is_customer) || authUser?.role === 'customer' || authUser?.role === 'user';
    const quotaLimit = guideMeta?.quota_limit ?? 5;
    const quotaRemaining = guideMeta?.quota_remaining;
    const quotaUsed = guideMeta?.quota_used;
    const quotaResetAt = guideMeta?.quota_reset_at;
    const diagnostic = guideMeta?.diagnostic;

    const currentCity = cityTerm || filters.city || 'tegucigalpa';
    const currentCategory = filters.category || '';

    const mergeMeta = (payload) => {
        const responseMeta = payload?.meta && typeof payload.meta === 'object'
            ? { ...payload.meta }
            : {};

        if (payload?.quota_limit !== undefined) {
            responseMeta.quota_limit = payload.quota_limit;
        }
        if (payload?.quota_remaining !== undefined) {
            responseMeta.quota_remaining = payload.quota_remaining;
        }
        if (payload?.reset_at && !responseMeta.quota_reset_at) {
            responseMeta.quota_reset_at = payload.reset_at;
        }

        return responseMeta;
    };

    const runGuide = async (event) => {
        event.preventDefault();

        if (!isCustomer || isSubmitting) {
            return;
        }

        setIsSubmitting(true);
        setGuideError(null);

        const query = new URLSearchParams();
        query.set('budget', budget);

        if ((searchTerm || '').trim()) {
            query.set('search', searchTerm.trim());
        }

        if ((cityTerm || filters.city || '').trim()) {
            query.set('city', cityTerm || filters.city);
        }

        if ((filters.category || '').trim()) {
            query.set('category', filters.category);
        }

        query.set('limit', '8');

        try {
            const response = await fetch(`/api/v1/directory/guide?${query.toString()}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                },
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch {
                payload = {};
            }

            const responseMeta = mergeMeta(payload);

            if (!response.ok) {
                setGuideMeta(Object.keys(responseMeta).length ? responseMeta : null);
                setGuideResults([]);
                setGuideError({
                    status: response.status,
                    code: payload?.error_code || 'DIRECTORY_GUIDE_REQUEST_FAILED',
                    message: payload?.message || 'No pudimos procesar la guia en este momento.',
                    retryAfterSeconds: payload?.retry_after_seconds || null,
                    resetAt: payload?.reset_at || responseMeta.quota_reset_at || null,
                });
                return;
            }

            setGuideMeta(responseMeta);
            setGuideResults(Array.isArray(payload?.data) ? payload.data : []);
        } catch {
            setGuideError({
                status: 0,
                code: 'DIRECTORY_GUIDE_NETWORK_ERROR',
                message: 'No fue posible conectar con la guia. Intenta nuevamente.',
                retryAfterSeconds: null,
                resetAt: null,
            });
        } finally {
            setIsSubmitting(false);
        }
    };

    const runChat = async (event) => {
        event.preventDefault();

        if (!isCustomer || isChatting || !chatPrompt.trim()) {
            return;
        }

        setIsChatting(true);
        setChatError(null);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch('/api/v1/directory/guide/chat', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'x-trace-id': `directory-chat-ui-${Date.now()}`,
                },
                body: JSON.stringify({
                    query: chatPrompt.trim(),
                    budget_amount: Number.parseFloat(budget || '0') || 0,
                    currency: 'HNL',
                    city: currentCity,
                    category: currentCategory,
                    limit: 5,
                }),
            });

            const payload = await response.json().catch(() => ({}));
            const responseMeta = mergeMeta(payload);
            setChatMeta(responseMeta);
            setGuideMeta(responseMeta);

            if (!response.ok) {
                setChatAnswer(null);
                setChatError({
                    status: response.status,
                    code: payload?.error_code || 'DIRECTORY_GUIDE_CHAT_FAILED',
                    message: payload?.message || 'No pudimos responder con IA en este momento.',
                    resetAt: payload?.reset_at || responseMeta.quota_reset_at || null,
                });

                return;
            }

            setChatAnswer(responseMeta.chat_answer || payload?.data?.answer || 'Te dejo opciones recomendadas con el presupuesto indicado.');
            setGuideResults(Array.isArray(payload?.data) ? payload.data : []);
        } catch {
            setChatError({
                status: 0,
                code: 'DIRECTORY_GUIDE_CHAT_NETWORK_ERROR',
                message: 'No fue posible conectar con el chat. Intenta nuevamente.',
                resetAt: null,
            });
        } finally {
            setIsChatting(false);
        }
    };

    if (!authUser) {
        return (
            <div className="mt-8 rounded-[28px] border border-stone-200 bg-white px-5 py-5 sm:px-6">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Guia personalizada</p>
                <h2 className="mt-2 text-xl font-semibold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                    Inicia sesion para usar la guia (5 consultas al dia)
                </h2>
                <p className="mt-2 text-sm text-slate-600">
                    La guia te recomienda negocios y productos con tu presupuesto, ciudad y categoria.
                </p>
                <div className="mt-4">
                    <Link
                        href={route('login')}
                        className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                    >
                        Entrar como customer
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            </div>
        );
    }

    if (!isCustomer) {
        return (
            <div className="mt-8 rounded-[28px] border border-amber-200 bg-amber-50 px-5 py-5 sm:px-6">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">Guia personalizada</p>
                <h2 className="mt-2 text-xl font-semibold text-amber-900" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                    Disponible solo para cuentas customer
                </h2>
                <p className="mt-2 text-sm text-amber-800/90">
                    Tu cuenta actual es operativa para panel interno. Usa una cuenta customer para consultas del directorio.
                </p>
            </div>
        );
    }

    return (
        <div className="mt-8 rounded-[28px] border border-stone-200 bg-white px-5 py-5 shadow-sm sm:px-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Guia personalizada</p>
                    <h2 className="mt-2 text-xl font-semibold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                        Recomendaciones por presupuesto
                    </h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Plan gratis: {quotaLimit} consultas por dia.
                    </p>
                </div>
                <div className="rounded-full bg-[#f3f3f1] px-4 py-2 text-sm font-semibold text-slate-700" data-testid="directory-guide-quota">
                    {quotaRemaining !== undefined && quotaRemaining !== null
                        ? `Consultas restantes: ${quotaRemaining}/${quotaLimit}`
                        : `Consultas restantes: ${quotaLimit}/${quotaLimit}`}
                </div>
            </div>

            <form onSubmit={runGuide} className="mt-5 grid gap-3 sm:grid-cols-[180px,1fr,auto]">
                <div>
                    <label htmlFor="directory-guide-budget" className="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                        Presupuesto (L)
                    </label>
                    <input
                        id="directory-guide-budget"
                        name="budget"
                        type="number"
                        min="1"
                        step="1"
                        value={budget}
                        onChange={(event) => setBudget(event.target.value)}
                        className="h-12 w-full rounded-2xl border border-stone-200 bg-[#f3f3f1] px-4 text-sm text-slate-900 outline-none transition focus:border-slate-950"
                        data-testid="directory-guide-budget-input"
                    />
                </div>
                <div className="self-end rounded-2xl border border-stone-200 bg-[#f3f3f1] px-4 py-3 text-sm text-slate-600">
                    Filtro actual: {filters.category || 'todas las categorias'} · {cityTerm || filters.city || 'todas las ciudades'}
                </div>
                <button
                    type="submit"
                    disabled={isSubmitting}
                    className="self-end inline-flex h-12 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                    data-testid="directory-guide-submit"
                >
                    {isSubmitting ? 'Consultando...' : 'Pedir guia'}
                </button>
            </form>

            {guideError ? (
                <div className="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" data-testid="directory-guide-error">
                    <p className="font-semibold">{guideError.message}</p>
                    {guideError.status === 429 ? (
                        <p className="mt-1">
                            Te desbloqueas en {formatQuotaResetAt(guideError.resetAt)}. Puedes volver manana o seguir explorando sin guia.
                        </p>
                    ) : null}
                </div>
            ) : null}

            {quotaUsed !== undefined && quotaUsed !== null ? (
                <p className="mt-3 text-xs text-slate-500">
                    Usadas hoy: {quotaUsed}/{quotaLimit}
                    {quotaResetAt ? ` · Reinicio: ${formatQuotaResetAt(quotaResetAt)}` : ''}
                </p>
            ) : null}

            {diagnostic ? (
                <div className="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700 sm:grid-cols-3">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Estado</p>
                        <p className="mt-1 font-semibold text-slate-900">{diagnostic.status}</p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Causa probable</p>
                        <p className="mt-1">{diagnostic.probable_cause}</p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Accion</p>
                        <p className="mt-1">{diagnostic.recommended_action}</p>
                        {diagnostic.trace_id ? (
                            <p className="mt-2 text-xs text-slate-500">REF: {diagnostic.trace_id}</p>
                        ) : null}
                    </div>
                </div>
            ) : null}

            {guideResults.length > 0 ? (
                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                    {guideResults.map((entry, index) => {
                        const tenant = resolveGuideTenant(entry);
                        const priceRange = entry?.price_range;

                        if (!tenant?.slug) {
                            return null;
                        }

                        return (
                            <Link
                                key={`${tenant.slug}-${index}`}
                                href={route('tenant.microsite.show', { tenant: tenant.slug })}
                                className="rounded-2xl border border-stone-200 bg-[#f8f8f6] px-4 py-4 transition hover:border-stone-300 hover:bg-white"
                                data-testid="directory-guide-result"
                            >
                                <p className="text-sm font-semibold text-slate-900">{tenant.name}</p>
                                <p className="mt-1 text-xs uppercase tracking-[0.12em] text-slate-500">
                                    {resolveTenantCategoryName(tenant)} · {resolveTenantCityName(tenant)}
                                </p>
                                {entry?.reason ? (
                                    <p className="mt-2 text-sm text-slate-600">{entry.reason}</p>
                                ) : null}
                                {priceRange?.min !== undefined && priceRange?.max !== undefined ? (
                                    <p className="mt-2 text-sm font-semibold text-slate-700">
                                        Rango sugerido: L {priceRange.min} - L {priceRange.max}
                                    </p>
                                ) : null}
                            </Link>
                        );
                    })}
                </div>
            ) : null}

            <div className="mt-6 overflow-hidden rounded-[28px] border border-slate-900/10 bg-slate-950 text-white shadow-2xl shadow-slate-950/10">
                <div className="relative px-5 py-5 sm:px-6">
                    <div className="pointer-events-none absolute right-0 top-0 h-32 w-32 rounded-full bg-lime-300/20 blur-3xl" />
                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-lime-200">Chat IA del directorio</p>
                    <h3 className="mt-2 text-2xl font-semibold" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                        Pregunta como hablaria un cliente real
                    </h3>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                        Ejemplo: "tengo 100 lps y quiero comer algo en Tegucigalpa". El chat responde con negocios publicados, presupuesto y trazabilidad.
                    </p>

                    <form onSubmit={runChat} className="mt-5 grid gap-3 lg:grid-cols-[1fr,auto]">
                        <textarea
                            value={chatPrompt}
                            onChange={(event) => setChatPrompt(event.target.value)}
                            rows={3}
                            className="min-h-[96px] rounded-3xl border border-white/10 bg-white/10 px-4 py-3 text-sm leading-6 text-white outline-none transition placeholder:text-slate-400 focus:border-lime-200"
                            placeholder="Que puedo comprar con 100 lps?"
                            data-testid="directory-guide-chat-input"
                        />
                        <button
                            type="submit"
                            disabled={isChatting || !chatPrompt.trim()}
                            className="inline-flex items-center justify-center rounded-3xl bg-lime-200 px-6 py-3 text-sm font-black text-slate-950 transition hover:bg-lime-100 disabled:cursor-not-allowed disabled:opacity-60"
                            data-testid="directory-guide-chat-submit"
                        >
                            {isChatting ? 'Pensando...' : 'Preguntar'}
                        </button>
                    </form>

                    {chatError ? (
                        <div className="mt-4 rounded-3xl border border-rose-300/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" data-testid="directory-guide-chat-error">
                            <p className="font-semibold">{chatError.message}</p>
                            {chatError.status === 429 ? (
                                <p className="mt-1">Tu cuota vuelve en {formatQuotaResetAt(chatError.resetAt)}.</p>
                            ) : null}
                        </div>
                    ) : null}

                    {chatAnswer ? (
                        <div className="mt-4 rounded-3xl border border-white/10 bg-white px-5 py-4 text-slate-900" data-testid="directory-guide-chat-answer">
                            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Respuesta</p>
                            <p className="mt-2 text-sm leading-7">{chatAnswer}</p>
                            {chatMeta?.trace_id ? (
                                <p className="mt-3 text-xs font-semibold text-slate-500">REF soporte: {chatMeta.trace_id}</p>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

export default function DirectoryIndex({ tenants, categories = [], cities = [], activeCity = null, filters = {} }) {
    const { auth = {} } = usePage().props;
    const authUser = auth?.user || null;
    const items = tenants?.data || tenants || [];
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [cityTerm, setCityTerm] = useState(filters.city || '');
    const [mobileOpen, setMobileOpen] = useState(false);

    const activeCategoryLabel = useMemo(() => (
        categories.find((category) => category.slug === filters.category)?.name || 'Todo Negocios'
    ), [categories, filters.category]);

    const quickCities = useMemo(() => cities.slice(0, 6), [cities]);

    const applyFilters = (nextFilters) => {
        router.get(route('directory.index'), cleanParams(nextFilters), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        const timer = window.setTimeout(() => {
            if (searchTerm !== (filters.search || '')) {
                applyFilters({ ...filters, search: searchTerm || null });
            }
        }, 400);

        return () => window.clearTimeout(timer);
    }, [searchTerm, filters]);

    const setCategory = (slug) => {
        applyFilters({ ...filters, category: slug || null });
        setMobileOpen(false);
    };

    const setCity = (slug) => {
        setCityTerm(slug);
        applyFilters({ ...filters, city: slug || null });
        setMobileOpen(false);
    };

    const clearFilters = () => {
        setSearchTerm('');
        setCityTerm('');
        applyFilters({});
        setMobileOpen(false);
    };

    const hasFilters = Boolean(filters.search || filters.category || filters.city);

    return (
        <PublicLayout>
            <Head title={`Negocios ${activeCity ? `en ${activeCity.name}` : 'en Honduras'}`}>
                <meta
                    name="description"
                    content="Busca negocios en Honduras por ciudad, categoria o termino de busqueda desde la capa publica unificada de Conecta HN."
                />
            </Head>

            <section className="border-b border-stone-200 bg-white">
                <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18">
                    <div className="max-w-3xl">
                        <p className="text-sm font-semibold uppercase tracking-[0.28em] text-slate-500">Negocios</p>
                        <h1 className="mt-4 text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                            Busca por ciudad, categoria o servicio sin salirte de la misma experiencia publica.
                        </h1>
                        <p className="mt-6 text-lg leading-8 text-slate-600">
                            Este es el modo completo de resultados: aqui comparas, filtras y aterrizas al negocio correcto. La operacion interna del negocio sigue en Filament.
                        </p>
                    </div>

                    <div className="mt-10 grid gap-4 lg:grid-cols-[1.15fr,0.45fr,auto]">
                        <div className="relative">
                            <label htmlFor="directory-search" className="sr-only">
                                Buscar negocios, productos o servicios
                            </label>
                            <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                id="directory-search"
                                name="search"
                                type="text"
                                placeholder="Buscar negocios, productos o servicios..."
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                className="h-14 w-full rounded-[24px] border border-stone-200 bg-[#f3f3f1] px-11 text-base text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-slate-950"
                            />
                        </div>

                        <select
                            value={cityTerm}
                            onChange={(event) => setCity(event.target.value)}
                            className="h-14 rounded-[24px] border border-stone-200 bg-[#f3f3f1] px-4 text-base text-slate-900 outline-none transition focus:border-slate-950"
                        >
                            <option value="">Todas las ciudades</option>
                            {cities.map((city) => (
                                <option key={city.id} value={city.slug}>{city.name}</option>
                            ))}
                        </select>

                        <button
                            type="button"
                            onClick={() => setMobileOpen((value) => !value)}
                            className="inline-flex h-14 items-center justify-center gap-2 rounded-[24px] border border-stone-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:border-stone-300 lg:hidden"
                        >
                            <Filter className="h-4 w-4" />
                            Filtros
                        </button>
                    </div>

                    <div className="mt-6 flex flex-wrap gap-3">
                        <button
                            type="button"
                            onClick={() => setCategory('')}
                            className={`rounded-full px-4 py-2 text-sm font-semibold transition ${filters.category ? 'border border-stone-200 bg-white text-slate-700 hover:border-stone-300' : 'bg-slate-950 text-white'}`}
                        >
                            Todo
                        </button>
                        {categories.map((category) => (
                            <button
                                key={category.id}
                                type="button"
                                onClick={() => setCategory(category.slug)}
                                className={`rounded-full px-4 py-2 text-sm font-semibold transition ${filters.category === category.slug ? 'bg-slate-950 text-white' : 'border border-stone-200 bg-white text-slate-700 hover:border-stone-300'}`}
                            >
                                {category.name}
                            </button>
                        ))}
                    </div>

                    {quickCities.length > 0 ? (
                        <div className="mt-6 flex flex-wrap items-center gap-3">
                            <span className="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Ciudades rapidas</span>
                            {quickCities.map((city) => (
                                <button
                                    key={city.id}
                                    type="button"
                                    onClick={() => setCity(city.slug)}
                                    className={`rounded-full border px-3 py-1.5 text-sm font-medium transition ${
                                        filters.city === city.slug
                                            ? 'border-slate-300 bg-[#f3f3f1] text-slate-950'
                                            : 'border-stone-200 bg-white text-slate-600 hover:border-stone-300 hover:text-slate-950'
                                    }`}
                                >
                                    {city.name}
                                </button>
                            ))}
                        </div>
                    ) : null}

                    <DirectoryGuidePanel
                        authUser={authUser}
                        filters={filters}
                        searchTerm={searchTerm}
                        cityTerm={cityTerm}
                    />

                    <div className="mt-8 flex flex-col gap-4 rounded-[28px] border border-stone-200 bg-[#f3f3f1] px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Vista actual</p>
                            <h2 className="mt-2 text-2xl font-semibold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                {activeCity ? `Negocios en ${activeCity.name}` : activeCategoryLabel}
                            </h2>
                            {filters.search ? (
                                <p className="mt-2 text-sm text-slate-600">
                                    Buscando: <span className="font-semibold text-slate-900">{filters.search}</span>
                                </p>
                            ) : null}
                        </div>

                        <div className="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                            <span className="rounded-full bg-white px-4 py-2 font-semibold text-slate-700">
                                {items.length} resultados
                            </span>
                            {hasFilters ? (
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="inline-flex items-center gap-2 rounded-full border border-stone-300 px-4 py-2 font-semibold transition hover:border-stone-400 hover:bg-white"
                                >
                                    <X className="h-4 w-4" />
                                    Limpiar
                                </button>
                            ) : null}
                        </div>
                    </div>
                </div>
            </section>

            <section className="bg-[#f3f3f1] py-16">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {items.length > 0 ? (
                        <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                            {items.map((tenant) => (
                                <DirectoryCard key={tenant.id} tenant={tenant} />
                            ))}
                        </div>
                    ) : (
                        <EmptyState filters={filters} onClear={clearFilters} />
                    )}

                    {tenants?.links && items.length > 0 ? (
                        <div className="mt-12 flex flex-wrap justify-center gap-2">
                            {tenants.links.map((link, index) => {
                                const label = link.label
                                    .replace('&laquo;', '<<')
                                    .replace('&raquo;', '>>')
                                    .replace('Previous', 'Anterior')
                                    .replace('Next', 'Siguiente');

                                return (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`rounded-full px-4 py-2 text-sm font-semibold transition ${link.active ? 'bg-slate-950 text-white' : 'border border-stone-200 bg-white text-slate-700 hover:border-stone-300'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    >
                                        {label}
                                    </Link>
                                );
                            })}
                        </div>
                    ) : null}
                </div>
            </section>

            {mobileOpen ? (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <button type="button" className="absolute inset-0 bg-black/40" onClick={() => setMobileOpen(false)} />
                    <div className="absolute inset-x-0 bottom-0 rounded-t-[32px] border border-stone-200 bg-white p-6 shadow-2xl">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                Filtros
                            </h2>
                            <button type="button" onClick={() => setMobileOpen(false)} className="rounded-full border border-stone-200 p-2 text-slate-600">
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <div className="mt-6 space-y-5">
                            <div>
                                <label className="text-sm font-semibold text-slate-700">Ciudad</label>
                                <select
                                    value={cityTerm}
                                    onChange={(event) => setCity(event.target.value)}
                                    className="mt-2 h-12 w-full rounded-2xl border border-stone-200 bg-[#f3f3f1] px-4 text-sm text-slate-900 outline-none focus:border-slate-950"
                                >
                                    <option value="">Todas las ciudades</option>
                                    {cities.map((city) => (
                                        <option key={city.id} value={city.slug}>{city.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="text-sm font-semibold text-slate-700">Categoria</label>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setCategory('')}
                                        className={`rounded-full px-4 py-2 text-sm font-semibold ${filters.category ? 'border border-stone-200 bg-white text-slate-700' : 'bg-slate-950 text-white'}`}
                                    >
                                        Todo
                                    </button>
                                    {categories.map((category) => (
                                        <button
                                            key={category.id}
                                            type="button"
                                            onClick={() => setCategory(category.slug)}
                                            className={`rounded-full px-4 py-2 text-sm font-semibold ${filters.category === category.slug ? 'bg-slate-950 text-white' : 'border border-stone-200 bg-white text-slate-700'}`}
                                        >
                                            {category.name}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <button
                                type="button"
                                onClick={clearFilters}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-stone-300 px-6 py-3 text-sm font-semibold text-slate-700"
                            >
                                <X className="h-4 w-4" />
                                Limpiar filtros
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}
        </PublicLayout>
    );
}
