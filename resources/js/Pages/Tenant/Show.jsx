import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import FollowButton from '@/Components/Public/FollowButton';
import ReviewSection from '@/Components/Public/ReviewSection';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import {
    ArrowUpRight,
    BadgeCheck,
    Clock3,
    ExternalLink,
    Globe,
    Lock,
    MapPin,
    MessageCircle,
    Phone,
    Search,
    ShoppingBag,
    Star,
    Store,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import FloatingCart from '@/Components/Tenant/FloatingCart';
import ProductActionArea from '@/Components/Tenant/ProductActionArea';
import useCartStore from '@/Stores/useCartStore';
import { buildWhatsAppUrl, isValidWhatsAppPhone } from '@/Utils/phoneUtils';
import { PUBLIC_BODY_FONT, PUBLIC_DISPLAY_FONT, PUBLIC_LABEL_FONT } from '@/lib/publicTypography';

const PLAN = { SEMILLA: 'semilla', PRO: 'pro', ENTERPRISE: 'enterprise' };
const PLAN_LABELS = {
    [PLAN.SEMILLA]: 'Plan Semilla',
    [PLAN.PRO]: 'Business Pro',
    [PLAN.ENTERPRISE]: 'Enterprise',
};
const FALLBACK_IMAGE = 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=1200&h=900&fit=crop';

function Eyebrow({ children, dark = false }) {
    return (
        <p
            className={`text-[11px] font-bold uppercase tracking-[0.22em] ${dark ? 'text-slate-300' : 'text-slate-500'}`}
            style={{ fontFamily: PUBLIC_LABEL_FONT }}
        >
            {children}
        </p>
    );
}

function SectionTitle({ children, className = '', dark = false }) {
    return (
        <h2
            className={`font-extrabold tracking-tight ${dark ? 'text-white' : 'text-slate-950'} ${className}`}
            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
        >
            {children}
        </h2>
    );
}

function TopNav({ isOwner = false, ownerPanelHref = null }) {
    return (
        <nav className="fixed inset-x-0 top-0 z-50 border-b border-slate-200/70 bg-white/84 backdrop-blur-[20px]">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link
                    href={route('public.explore')}
                    className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800"
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    <Store className="h-4 w-4" />
                    Negocios
                </Link>

                <Link href={route('home')} className="text-center">
                    <span
                        className="block text-lg font-extrabold tracking-tight text-slate-950 sm:text-xl"
                        style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                    >
                        Vito Business OS
                    </span>
                    <span
                        className="hidden text-[10px] font-semibold uppercase tracking-[0.28em] text-slate-500 sm:block"
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        Conecta HN
                    </span>
                </Link>

                <div className="flex items-center gap-3">
                    {isOwner && ownerPanelHref ? (
                        <a
                            href={ownerPanelHref}
                            className="hidden items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-900 transition hover:border-slate-300 hover:bg-slate-100 sm:inline-flex"
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            Abrir panel
                            <ArrowUpRight className="h-4 w-4" />
                        </a>
                    ) : null}
                </div>
            </div>
        </nav>
    );
}

function HeroSection({
    tenant,
    tenantPlan,
    cityName,
    isOpen,
    visibleTotal,
    totalProductsCount,
    hiddenProductsCount,
    reviewCount,
    followersCount,
    primaryWhatsAppUrl,
    isFollowing,
    isOwner,
    ownerPanelHref,
    openingHours = [],
    hasWebsite = false,
}) {
    const coverImage = tenant.cover || tenant.hero_image || tenant.banner_path || FALLBACK_IMAGE;
    const description = tenant.description || tenant.slogan || `Descubre ${tenant.name} dentro de Conecta HN.`;

    return (
        <header className="px-6 pb-10 pt-24 md:pb-14">
            <div className="mx-auto max-w-7xl">
                <div className="relative overflow-hidden rounded-[2.25rem] border border-slate-200 bg-slate-100">
                    <div className="absolute inset-0">
                        <img
                            src={coverImage}
                            alt={tenant.name}
                            className="h-full w-full object-cover grayscale"
                            onError={(event) => {
                                event.target.onerror = null;
                                event.target.src = FALLBACK_IMAGE;
                            }}
                        />
                        <div className="absolute inset-0 bg-[linear-gradient(120deg,rgba(255,255,255,0.96)_10%,rgba(255,255,255,0.82)_42%,rgba(255,255,255,0.18)_100%)]" />
                    </div>

                    <div className="relative grid gap-8 px-6 py-8 lg:grid-cols-[minmax(0,1fr)_320px] lg:p-10">
                        <div className="space-y-8">
                            <div className="flex flex-col gap-6 sm:flex-row sm:items-start">
                                <div className="flex h-28 w-28 items-center justify-center overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-lg shadow-slate-950/8">
                                    {tenant.logo ? (
                                        <img
                                            src={tenant.logo}
                                            alt={`${tenant.name} logo`}
                                            className="h-full w-full object-cover grayscale"
                                        />
                                    ) : (
                                        <span
                                            className="text-3xl font-extrabold uppercase tracking-[0.12em] text-slate-950"
                                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                        >
                                            {tenant.name?.slice(0, 2).toUpperCase()}
                                        </span>
                                    )}
                                </div>

                                <div className="flex-1 space-y-5">
                                    <div className="flex flex-wrap items-center gap-3">
                                        <span
                                            className="rounded-full bg-slate-200 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-900"
                                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                        >
                                            {tenant.category?.name || 'Negocio local'}
                                        </span>
                                        <span
                                            className="rounded-full bg-white px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500"
                                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                        >
                                            {PLAN_LABELS[tenantPlan] || tenantPlan}
                                        </span>
                                        {tenantPlan !== PLAN.SEMILLA ? (
                                            <span
                                                className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-white"
                                                style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                            >
                                                <BadgeCheck className="h-3.5 w-3.5" />
                                                Verificado
                                            </span>
                                        ) : null}
                                    </div>

                                    <div>
                                        <h1
                                            className="max-w-3xl text-4xl font-extrabold leading-[1.03] tracking-tight text-slate-950 md:text-6xl"
                                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                        >
                                            {tenant.name}
                                        </h1>
                                        <p className="mt-4 max-w-2xl text-lg leading-8 text-slate-600">
                                            {description}
                                        </p>
                                    </div>

                                    <div
                                        className="flex flex-wrap items-center gap-x-5 gap-y-3 text-sm font-semibold text-slate-600"
                                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                    >
                                        <span className="inline-flex items-center gap-2">
                                            <MapPin className="h-4 w-4 text-slate-950" />
                                            {cityName}
                                        </span>
                                        <span className="inline-flex items-center gap-2">
                                            <span className={`h-2.5 w-2.5 rounded-full ${isOpen ? 'bg-slate-950' : 'bg-slate-300'}`} />
                                            {isOpen ? 'Abierto ahora' : 'Cerrado'}
                                        </span>
                                        {reviewCount > 0 ? (
                                            <span className="inline-flex items-center gap-2">
                                                <Star className="h-4 w-4 fill-slate-950 text-slate-950" />
                                                {tenant.rating || '4.8'} ({reviewCount})
                                            </span>
                                        ) : null}
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3">
                                        {!isOwner ? (
                                            <FollowButton
                                                tenantId={tenant.id}
                                                isFollowing={isFollowing}
                                                followersCount={followersCount}
                                                className="!rounded-full !border-slate-950 !bg-white !px-5 !py-3 !text-xs !font-semibold !uppercase !tracking-[0.16em] !text-slate-950 hover:!bg-slate-100"
                                            />
                                        ) : null}

                                        {primaryWhatsAppUrl ? (
                                            <a
                                                href={primaryWhatsAppUrl}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800"
                                                style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                            >
                                                <MessageCircle className="h-4 w-4" />
                                                WhatsApp
                                            </a>
                                        ) : null}

                                        {tenant.phone ? (
                                            <a
                                                href={`tel:${tenant.phone}`}
                                                className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-950 transition hover:border-slate-300 hover:bg-slate-100"
                                                style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                            >
                                                <Phone className="h-4 w-4" />
                                                Llamar
                                            </a>
                                        ) : null}

                                        {hasWebsite ? (
                                            <a
                                                href={tenant.website_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-950 transition hover:border-slate-300 hover:bg-slate-100"
                                                style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                            >
                                                <Globe className="h-4 w-4" />
                                                Sitio web
                                            </a>
                                        ) : null}

                                        {isOwner && ownerPanelHref ? (
                                            <a
                                                href={ownerPanelHref}
                                                className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-950 transition hover:border-slate-300 hover:bg-slate-100"
                                                style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                            >
                                                Administrar
                                                <ArrowUpRight className="h-4 w-4" />
                                            </a>
                                        ) : null}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <aside className="rounded-[1.8rem] border border-white/50 bg-white/78 p-6 shadow-2xl shadow-slate-950/10 backdrop-blur-md">
                            <Eyebrow>Vista operativa</Eyebrow>
                            <SectionTitle className="mt-3 text-2xl">Presencia lista para vender</SectionTitle>
                            <p className="mt-3 text-sm leading-7 text-slate-600">
                                Catalogo, contacto directo y datos clave del negocio en una sola experiencia publica.
                            </p>

                            <div className="mt-6 grid grid-cols-2 gap-3">
                                <MetricCard label="Catalogo visible" value={visibleTotal} />
                                <MetricCard label="Total cargado" value={totalProductsCount} />
                                <MetricCard label="Seguidores" value={followersCount || 0} />
                                <MetricCard label="Ocultos" value={hiddenProductsCount || 0} muted={hiddenProductsCount === 0} />
                            </div>

                            <div className="mt-6 rounded-[1.4rem] bg-slate-950 p-5 text-white">
                                <Eyebrow dark>{isOpen ? 'Disponible hoy' : 'Estado actual'}</Eyebrow>
                                <p
                                    className="mt-3 text-xl font-bold"
                                    style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                >
                                    {isOpen ? 'Abierto para atender' : 'Fuera de horario'}
                                </p>
                                <div className="mt-4 space-y-3 text-sm text-slate-300">
                                    <p className="inline-flex items-center gap-2">
                                        <Clock3 className="h-4 w-4" />
                                        {openingHours[0]?.hours || openingHours[0]?.value || 'Consulta horarios en informacion'}
                                    </p>
                                    <p className="inline-flex items-center gap-2">
                                        <MapPin className="h-4 w-4" />
                                        {tenant.address || cityName}
                                    </p>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </header>
    );
}

function MetricCard({ label, value, muted = false }) {
    return (
        <div className={`rounded-[1.25rem] px-4 py-4 ${muted ? 'bg-slate-100' : 'bg-white'}`}>
            <p
                className="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500"
                style={{ fontFamily: PUBLIC_LABEL_FONT }}
            >
                {label}
            </p>
            <p
                className="mt-2 text-2xl font-extrabold tracking-tight text-slate-950"
                style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
            >
                {value}
            </p>
        </div>
    );
}

function PreviewBanner({ previewReason, ownerPanelHref }) {
    return (
        <section className="px-6 pb-6">
            <div className="mx-auto max-w-7xl rounded-[1.75rem] bg-slate-950 px-6 py-5 text-white">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <Eyebrow dark>Vista previa privada</Eyebrow>
                        <p className="mt-2 text-sm leading-7 text-slate-300">
                            {previewReason || 'Este negocio aun no esta visible para el publico general.'}
                        </p>
                    </div>
                    {ownerPanelHref ? (
                        <a
                            href={ownerPanelHref}
                            className="inline-flex items-center justify-center gap-2 rounded-full bg-white px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-950 transition hover:bg-slate-200"
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            Abrir panel
                            <ArrowUpRight className="h-4 w-4" />
                        </a>
                    ) : null}
                </div>
            </div>
        </section>
    );
}

function CatalogToolbar({
    searchQuery,
    setSearchQuery,
    handleSearch,
    visibleTotal,
    currentCount,
    hiddenProductsCount,
    hasHiddenProducts,
}) {
    return (
        <section id="catalogo" className="rounded-[1.9rem] bg-slate-50 p-6 md:p-8">
            <div className="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div className="max-w-2xl">
                    <Eyebrow>Catalogo publico</Eyebrow>
                    <SectionTitle className="mt-3 text-3xl">Productos disponibles</SectionTitle>
                    <p className="mt-3 text-base leading-7 text-slate-600">
                        Mostrando {currentCount} de {visibleTotal} productos visibles para visitantes.
                    </p>
                </div>

                <form onSubmit={handleSearch} className="w-full max-w-xl">
                    <div className="flex items-center gap-3 rounded-full border border-slate-200 bg-white px-4 py-3 shadow-sm shadow-slate-950/5">
                        <Search className="h-4 w-4 text-slate-400" />
                        <input
                            type="search"
                            value={searchQuery}
                            onChange={(event) => setSearchQuery(event.target.value)}
                            placeholder="Buscar por nombre o descripcion"
                            className="min-w-0 flex-1 bg-transparent text-sm text-slate-950 outline-none placeholder:text-slate-400"
                        />
                        <button
                            type="submit"
                            className="rounded-full bg-slate-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800"
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            Buscar
                        </button>
                    </div>
                </form>
            </div>

            {hasHiddenProducts ? (
                <div className="mt-6 flex items-start gap-3 rounded-[1.4rem] bg-white px-5 py-4 shadow-sm shadow-slate-950/5">
                    <Lock className="mt-1 h-4 w-4 text-slate-950" />
                    <p className="text-sm leading-7 text-slate-600">
                        Hay {hiddenProductsCount} productos ocultos por limite de plan.
                    </p>
                </div>
            ) : null}
        </section>
    );
}

function ProductGridSection({
    products,
    pagination = [],
    tenant,
    tenantPlan,
    hiddenProductsCount = 0,
    hasHiddenProducts = false,
    isOwner = false,
}) {
    const ownerPanelHref = tenant?.slug ? route('filament.app.pages.dashboard', { tenant: tenant.slug }) : null;

    if (!products?.length) {
        return (
            <div className="rounded-[1.9rem] bg-slate-100 p-12 text-center">
                <ShoppingBag className="mx-auto mb-4 h-10 w-10 text-slate-950" />
                <SectionTitle className="text-2xl">No se encontraron productos</SectionTitle>
                <p className="mt-3 text-sm text-slate-500">
                    Intenta con otra busqueda o revisa de nuevo mas tarde.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-10">
            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                {products.map((product) => (
                    <ProductCard
                        key={product.id}
                        product={product}
                        tenant={tenant}
                        tenantPlan={tenantPlan}
                    />
                ))}
            </div>

            {pagination.length > 3 ? <Pagination pagination={pagination} /> : null}

            {hasHiddenProducts ? (
                <div className="rounded-[1.6rem] bg-slate-950 px-6 py-5 text-white">
                    <Eyebrow dark>Limite de plan</Eyebrow>
                    <p
                        className="mt-3 text-2xl font-bold"
                        style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                    >
                        {hiddenProductsCount} productos siguen fuera del escaparate
                    </p>
                    <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-300">
                        Amplia el plan para publicar mas articulos sin recortar la experiencia del visitante.
                    </p>
                    {isOwner && ownerPanelHref ? (
                        <a
                            href={ownerPanelHref}
                            className="mt-5 inline-flex items-center gap-2 rounded-full bg-white px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-950 transition hover:bg-slate-200"
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            Administrar plan
                            <ArrowUpRight className="h-4 w-4" />
                        </a>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}

function ProductCard({ product, tenant, tenantPlan }) {
    const hasModifiers = product.has_modifiers || (product.modifier_groups?.length > 0);

    return (
        <article className="group flex h-full flex-col overflow-hidden rounded-[1.85rem] border border-slate-200 bg-white shadow-sm shadow-slate-950/5 transition duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-slate-950/10">
            <div className="relative aspect-[4/3] overflow-hidden bg-slate-100">
                <img
                    src={product.image || FALLBACK_IMAGE}
                    alt={product.name}
                    className="h-full w-full object-cover grayscale transition duration-700 group-hover:scale-105 group-hover:grayscale-0"
                    loading="lazy"
                    onError={(event) => {
                        event.target.onerror = null;
                        event.target.src = FALLBACK_IMAGE;
                    }}
                />
                <div className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-slate-950/35 to-transparent" />
                <div
                    className="absolute right-4 top-4 rounded-full bg-white/92 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-950 shadow-sm"
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    {hasModifiers && tenantPlan !== PLAN.SEMILLA ? 'Desde ' : ''}
                    {product.formatted_price || `L. ${product.price}`}
                </div>
                {hasModifiers && tenantPlan !== PLAN.SEMILLA ? (
                    <div
                        className="absolute bottom-4 left-4 rounded-full bg-slate-950 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-white"
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        {product.modifier_groups?.length || 0} opciones
                    </div>
                ) : null}
            </div>

            <div className="flex flex-1 flex-col gap-5 p-6">
                <div className="space-y-3">
                    <h3
                        className="line-clamp-2 text-2xl font-bold tracking-tight text-slate-950"
                        style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                    >
                        {product.name}
                    </h3>
                    <p className="min-h-[4rem] text-sm leading-7 text-slate-600">
                        {product.description || 'Producto disponible dentro del escaparate publico del negocio.'}
                    </p>
                </div>

                <ProductCta product={product} tenant={tenant} tenantPlan={tenantPlan} />
            </div>
        </article>
    );
}

function ProductCta({ product, tenant, tenantPlan }) {
    if (tenantPlan === PLAN.SEMILLA) {
        const phone = tenant.whatsapp || tenant.whatsapp_number || '';
        const url = buildWhatsAppUrl(phone, `Hola ${tenant.name || 'equipo'}, me interesa ${product?.name || 'este producto'}.`);

        if (!isValidWhatsAppPhone(phone) || !url) {
            return (
                <div
                    className="inline-flex w-full items-center justify-center rounded-xl bg-slate-100 px-4 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400"
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    No disponible
                </div>
            );
        }

        return (
            <a
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800"
                style={{ fontFamily: PUBLIC_LABEL_FONT }}
            >
                <MessageCircle className="h-4 w-4" />
                Consultar
            </a>
        );
    }

    return (
        <ProductActionArea
            product={product}
            tenantId={tenant.id}
            tenantPlan={tenantPlan}
            tenantWhatsapp={tenant.whatsapp || tenant.whatsapp_number}
            tenantName={tenant.name}
            tenantSlug={tenant.slug}
            variant="default"
            className="!w-full !rounded-xl !bg-slate-950 !text-white !shadow-none hover:!bg-slate-800 !text-xs !font-semibold !uppercase !tracking-[0.16em]"
        />
    );
}

function Pagination({ pagination }) {
    return (
        <nav className="flex flex-wrap items-center justify-center gap-2">
            {pagination.map((link, index) => {
                const key = `${link.label}-${index}`;
                const content = (
                    <span
                        dangerouslySetInnerHTML={{
                            __html: String(link.label || '').replace('&laquo;', '<<').replace('&raquo;', '>>'),
                        }}
                    />
                );

                if (!link.url) {
                    return (
                        <span
                            key={key}
                            className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-300"
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            {content}
                        </span>
                    );
                }

                return (
                    <Link
                        key={key}
                        href={link.url}
                        preserveState
                        preserveScroll
                        className={cn(
                            'rounded-full border px-4 py-2 text-xs font-semibold uppercase tracking-[0.14em] transition',
                            link.active
                                ? 'border-slate-950 bg-slate-950 text-white'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-100'
                        )}
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        {content}
                    </Link>
                );
            })}
        </nav>
    );
}

function InfoSection({ tenant, isOpen, openingHours = [] }) {
    return (
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1.65fr)_360px]">
            <article className="rounded-[1.9rem] bg-slate-50 p-8 md:p-10">
                <Eyebrow>Presentacion</Eyebrow>
                <SectionTitle className="mt-3 text-3xl">Sobre {tenant.name}</SectionTitle>
                <p className="mt-6 whitespace-pre-line text-base leading-8 text-slate-600">
                    {tenant.description || 'Bienvenido a nuestro negocio.'}
                </p>
            </article>

            <div className="space-y-6">
                <article className="rounded-[1.6rem] bg-white p-6 shadow-sm shadow-slate-950/5">
                    <Eyebrow>Informacion clave</Eyebrow>
                    <div className="mt-5 space-y-5 text-sm text-slate-600">
                        <div className="flex items-start gap-4">
                            <MapPin className="mt-1 h-4 w-4 text-slate-950" />
                            <div>
                                <p className="font-semibold text-slate-950">Ubicacion</p>
                                <p className="mt-1 leading-7">{tenant.address || 'No especificada'}</p>
                            </div>
                        </div>

                        <div className="flex items-start gap-4">
                            <Clock3 className="mt-1 h-4 w-4 text-slate-950" />
                            <div>
                                <p className="font-semibold text-slate-950">Estado</p>
                                <p className="mt-1 leading-7">{isOpen ? 'Abierto ahora' : 'Cerrado'}</p>
                            </div>
                        </div>

                        {tenant.phone ? (
                            <div className="flex items-start gap-4">
                                <Phone className="mt-1 h-4 w-4 text-slate-950" />
                                <div>
                                    <p className="font-semibold text-slate-950">Telefono</p>
                                    <a href={`tel:${tenant.phone}`} className="mt-1 inline-flex leading-7 text-slate-600 underline decoration-slate-300 underline-offset-4 transition hover:text-slate-950">
                                        {tenant.phone}
                                    </a>
                                </div>
                            </div>
                        ) : null}

                        {tenant.website_url ? (
                            <div className="flex items-start gap-4">
                                <Globe className="mt-1 h-4 w-4 text-slate-950" />
                                <div>
                                    <p className="font-semibold text-slate-950">Sitio web</p>
                                    <a
                                        href={tenant.website_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="mt-1 inline-flex items-center gap-2 leading-7 text-slate-600 underline decoration-slate-300 underline-offset-4 transition hover:text-slate-950"
                                    >
                                        Visitar
                                        <ExternalLink className="h-4 w-4" />
                                    </a>
                                </div>
                            </div>
                        ) : null}
                    </div>
                </article>

                {openingHours.length > 0 ? (
                    <article className="rounded-[1.6rem] bg-slate-950 p-6 text-white">
                        <Eyebrow dark>Horarios</Eyebrow>
                        <div className="mt-5 space-y-4">
                            {openingHours.map((slot, index) => (
                                <div key={`${slot.day || slot.label || 'day'}-${index}`} className="flex items-center justify-between gap-4 text-sm">
                                    <span className="font-semibold text-white">
                                        {slot.day || slot.label || `Dia ${index + 1}`}
                                    </span>
                                    <span className="text-slate-300">
                                        {slot.hours || slot.open || slot.value || 'Consultar'}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </article>
                ) : null}
            </div>
        </div>
    );
}

function ReviewsPanel({ tenant }) {
    return (
        <section className="rounded-[1.9rem] bg-slate-50 p-6 md:p-8">
            <ReviewSection tenant={tenant} reviews={tenant.reviews || []} />
        </section>
    );
}

function MicrositeFooter({ tenant, primaryWhatsAppUrl, tenantPlan }) {
    return (
        <footer className="border-t border-slate-200 bg-white py-10">
            <div className="mx-auto flex max-w-7xl flex-col gap-5 px-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <p
                        className="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500"
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        Impulsado por Vito Business OS
                    </p>
                    <h3
                        className="mt-3 text-2xl font-bold text-slate-950"
                        style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                    >
                        {tenant.name}
                    </h3>
                    <p className="mt-2 max-w-xl text-sm leading-7 text-slate-600">
                        Micrositio conectado a Conecta HN con escaparate publico, contacto directo y operacion lista para crecer.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    {primaryWhatsAppUrl ? (
                        <a
                            href={primaryWhatsAppUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800"
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            <MessageCircle className="h-4 w-4" />
                            Contactar
                        </a>
                    ) : null}

                    <Link
                        href={route('tenant.register')}
                        className={`inline-flex items-center gap-2 rounded-full border border-slate-200 px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] transition hover:border-slate-300 hover:bg-slate-100 ${tenantPlan === PLAN.SEMILLA ? 'text-slate-950' : 'text-slate-700'}`}
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        Crear mi negocio
                        <ArrowUpRight className="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </footer>
    );
}

function MobileDock({ micrositeHref, tenant, primaryWhatsAppUrl }) {
    return (
        <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur-xl md:hidden">
            <div className="grid h-16 grid-cols-4">
                <Link
                    href={micrositeHref}
                    className="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 transition hover:text-slate-950"
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    <Store className="h-4 w-4" />
                    <span>Inicio</span>
                </Link>
                <a
                    href="#catalogo"
                    className="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 transition hover:text-slate-950"
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    <ShoppingBag className="h-4 w-4" />
                    <span>Catalogo</span>
                </a>
                {tenant.phone ? (
                    <a
                        href={`tel:${tenant.phone}`}
                        className="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 transition hover:text-slate-950"
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        <Phone className="h-4 w-4" />
                        <span>Llamar</span>
                    </a>
                ) : (
                    <div />
                )}
                {primaryWhatsAppUrl ? (
                    <a
                        href={primaryWhatsAppUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-950"
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        <MessageCircle className="h-4 w-4" />
                        <span>WhatsApp</span>
                    </a>
                ) : (
                    <div />
                )}
            </div>
        </nav>
    );
}

export default function TenantShow({
    tenant,
    products,
    filters = {},
    tenantPlan = PLAN.SEMILLA,
    hasHiddenProducts = false,
    totalProductsCount = 0,
    hiddenProductsCount = 0,
    isOwner = false,
    isFollowing,
    followersCount,
    seoStructuredData,
    isOpen = true,
    openingHours = [],
    canonicalUrl,
    previewMode = false,
    previewReason = null,
}) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const initForTenant = useCartStore((state) => state.initForTenant);

    useEffect(() => {
        setSearchQuery(filters.search || '');
    }, [filters.search]);

    useEffect(() => {
        if (tenantPlan !== PLAN.SEMILLA && tenant?.slug) {
            initForTenant(tenant.slug, tenant.whatsapp || tenant.whatsapp_number, tenant.name, tenant.id);
        }
    }, [initForTenant, tenant, tenantPlan]);

    if (!tenant) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-white text-black">
                <Store className="h-8 w-8" />
            </div>
        );
    }

    const safeProducts = Array.isArray(products?.data)
        ? products.data
        : Array.isArray(products)
            ? products
            : Array.isArray(tenant.products)
                ? tenant.products
                : [];
    const pagination = Array.isArray(products?.links) ? products.links : [];
    const visibleTotal = Number(products?.total ?? safeProducts.length);
    const whatsapp = tenant.whatsapp || tenant.whatsapp_number || '';
    const cityName = typeof tenant.city === 'string' ? tenant.city : (tenant.city?.name || 'Honduras');
    const micrositeHref = route('tenant.microsite.show', { tenant: tenant.slug });
    const ownerPanelHref = tenant?.slug ? route('filament.app.pages.dashboard', { tenant: tenant.slug }) : null;
    const reviewCount = Number(tenant.reviews_count ?? (Array.isArray(tenant.reviews) ? tenant.reviews.length : 0));
    const primaryWhatsAppUrl = isValidWhatsAppPhone(whatsapp)
        ? buildWhatsAppUrl(whatsapp, `Hola ${tenant.name}!`)
        : null;
    const pageTitle = `${tenant.name || 'Tienda'} | ${tenant.category?.name || 'Negocios'} en ${cityName} | Vito Business OS`;
    const pageDescription = String(
        tenant.description || tenant.slogan || `Descubre ${tenant.name} dentro de Conecta HN.`
    ).substring(0, 160);
    const pageImage = tenant.hero_image || tenant.cover || tenant.banner_path || FALLBACK_IMAGE;

    const handleSearch = useCallback((event) => {
        event.preventDefault();
        router.get(
            micrositeHref,
            { search: searchQuery || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, [micrositeHref, searchQuery]);

    const infoPanel = useMemo(() => (
        <InfoSection tenant={tenant} isOpen={isOpen} openingHours={openingHours} />
    ), [tenant, isOpen, openingHours]);

    const reviewsPanel = useMemo(() => (
        <ReviewsPanel tenant={tenant} />
    ), [tenant]);

    return (
        <div
            className="min-h-screen bg-white text-slate-950 selection:bg-slate-950 selection:text-white"
            style={{
                fontFamily: PUBLIC_BODY_FONT,
                '--primary': '#020617',
                '--primary-color': '#020617',
                '--primary-text-color': '#ffffff',
            }}
        >
            <Head title={pageTitle}>
                <meta name="description" content={pageDescription} />
                <meta property="og:title" content={pageTitle} />
                <meta property="og:description" content={pageDescription} />
                <meta property="og:type" content="business.business" />
                <meta property="og:image" content={pageImage} />
                {canonicalUrl ? <link rel="canonical" href={canonicalUrl} /> : null}
                {seoStructuredData ? (
                    <script
                        type="application/ld+json"
                        dangerouslySetInnerHTML={{ __html: JSON.stringify(seoStructuredData) }}
                    />
                ) : null}
            </Head>

            <TopNav isOwner={isOwner} ownerPanelHref={ownerPanelHref} />

            <HeroSection
                tenant={tenant}
                tenantPlan={tenantPlan}
                cityName={cityName}
                isOpen={isOpen}
                visibleTotal={visibleTotal}
                totalProductsCount={totalProductsCount}
                hiddenProductsCount={hiddenProductsCount}
                reviewCount={reviewCount}
                followersCount={followersCount}
                primaryWhatsAppUrl={primaryWhatsAppUrl}
                isFollowing={isFollowing}
                isOwner={isOwner}
                ownerPanelHref={ownerPanelHref}
                openingHours={openingHours}
                hasWebsite={Boolean(tenant.website_url)}
            />

            {previewMode ? (
                <PreviewBanner previewReason={previewReason} ownerPanelHref={ownerPanelHref} />
            ) : null}

            {tenantPlan !== PLAN.SEMILLA ? (
                <Tabs defaultValue="products" className="w-full">
                    <div className="sticky top-16 z-30 border-b border-slate-200/70 bg-white/92 backdrop-blur-xl">
                        <div className="mx-auto max-w-7xl px-6 py-4">
                            <TabsList className="grid h-auto w-full max-w-xl grid-cols-3 rounded-full bg-slate-100 p-1">
                                <TabsTrigger
                                    value="products"
                                    className="rounded-full px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 data-[state=active]:bg-white data-[state=active]:text-slate-950"
                                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                >
                                    Catalogo
                                </TabsTrigger>
                                <TabsTrigger
                                    value="info"
                                    className="rounded-full px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 data-[state=active]:bg-white data-[state=active]:text-slate-950"
                                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                >
                                    Informacion
                                </TabsTrigger>
                                <TabsTrigger
                                    value="reviews"
                                    className="rounded-full px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 data-[state=active]:bg-white data-[state=active]:text-slate-950"
                                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                >
                                    Resenas
                                </TabsTrigger>
                            </TabsList>
                        </div>
                    </div>

                    <main className="mx-auto max-w-7xl space-y-10 px-6 py-10 pb-32">
                        <TabsContent value="products" className="mt-0 space-y-8">
                            <CatalogToolbar
                                searchQuery={searchQuery}
                                setSearchQuery={setSearchQuery}
                                handleSearch={handleSearch}
                                visibleTotal={visibleTotal}
                                currentCount={safeProducts.length}
                                hiddenProductsCount={hiddenProductsCount}
                                hasHiddenProducts={hasHiddenProducts}
                            />
                            <ProductGridSection
                                products={safeProducts}
                                pagination={pagination}
                                tenant={tenant}
                                tenantPlan={tenantPlan}
                                hiddenProductsCount={hiddenProductsCount}
                                hasHiddenProducts={hasHiddenProducts}
                                isOwner={isOwner}
                            />
                        </TabsContent>

                        <TabsContent value="info" className="mt-0">
                            {infoPanel}
                        </TabsContent>

                        <TabsContent value="reviews" className="mt-0">
                            {reviewsPanel}
                        </TabsContent>
                    </main>
                </Tabs>
            ) : (
                <main className="mx-auto max-w-7xl space-y-10 px-6 py-10 pb-32">
                    <CatalogToolbar
                        searchQuery={searchQuery}
                        setSearchQuery={setSearchQuery}
                        handleSearch={handleSearch}
                        visibleTotal={visibleTotal}
                        currentCount={safeProducts.length}
                        hiddenProductsCount={hiddenProductsCount}
                        hasHiddenProducts={hasHiddenProducts}
                    />
                    <ProductGridSection
                        products={safeProducts}
                        pagination={pagination}
                        tenant={tenant}
                        tenantPlan={tenantPlan}
                        hiddenProductsCount={hiddenProductsCount}
                        hasHiddenProducts={hasHiddenProducts}
                        isOwner={isOwner}
                    />
                    {infoPanel}
                    {reviewsPanel}
                </main>
            )}

            {tenantPlan !== PLAN.SEMILLA ? <FloatingCart /> : null}
            <MobileDock micrositeHref={micrositeHref} tenant={tenant} primaryWhatsAppUrl={primaryWhatsAppUrl} />
            <MicrositeFooter tenant={tenant} primaryWhatsAppUrl={primaryWhatsAppUrl} tenantPlan={tenantPlan} />
        </div>
    );
}
