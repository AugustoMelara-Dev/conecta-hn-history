import { Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import FollowButton from '@/Components/Public/FollowButton';
import ReviewSection from '@/Components/Public/ReviewSection';
import FloatingCart from '@/Components/Tenant/FloatingCart';
import ProductActionArea from '@/Components/Tenant/ProductActionArea';
import useCartStore from '@/Stores/useCartStore';
import { buildWhatsAppUrl, isValidWhatsAppPhone } from '@/Utils/phoneUtils';
import { cn } from '@/lib/utils';
import { PUBLIC_BODY_FONT, PUBLIC_DISPLAY_FONT, PUBLIC_LABEL_FONT } from '@/lib/publicTypography';
import {
    ArrowUpRight,
    BadgeCheck,
    CalendarClock,
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
    Users,
    UtensilsCrossed,
} from 'lucide-react';

const PLAN = { SEMILLA: 'semilla', PRO: 'pro', ENTERPRISE: 'enterprise' };
const PLAN_LABELS = {
    [PLAN.SEMILLA]: 'Plan Semilla',
    [PLAN.PRO]: 'Business Pro',
    [PLAN.ENTERPRISE]: 'Enterprise',
};
const FALLBACK_IMAGE = 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=1200&h=900&fit=crop';

const BUSINESS_THEMES = {
    retail: {
        label: 'Retail',
        accentClass: 'from-sky-500/45 via-cyan-400/12 to-transparent',
        chipClass: 'bg-sky-100 text-sky-950',
        icon: ShoppingBag,
    },
    food: {
        label: 'Food',
        accentClass: 'from-amber-500/50 via-orange-500/14 to-transparent',
        chipClass: 'bg-amber-100 text-amber-950',
        icon: UtensilsCrossed,
    },
    service: {
        label: 'Service',
        accentClass: 'from-emerald-500/45 via-teal-400/12 to-transparent',
        chipClass: 'bg-emerald-100 text-emerald-950',
        icon: CalendarClock,
    },
    hybrid: {
        label: 'Hybrid',
        accentClass: 'from-fuchsia-500/35 via-violet-500/14 to-transparent',
        chipClass: 'bg-violet-100 text-violet-950',
        icon: Store,
    },
};

function stripPaginationLabel(label) {
    return String(label ?? '')
        .replace(/&laquo;/g, 'Anterior')
        .replace(/&raquo;/g, 'Siguiente')
        .replace(/<[^>]+>/g, '')
        .trim();
}

function Eyebrow({ children, dark = false }) {
    return (
        <p
            className={cn(
                'text-[11px] font-bold uppercase tracking-[0.22em]',
                dark ? 'text-white/70' : 'text-slate-500'
            )}
            style={{ fontFamily: PUBLIC_LABEL_FONT }}
        >
            {children}
        </p>
    );
}

function SectionTitle({ children, dark = false, className = '' }) {
    return (
        <h2
            className={cn(
                'font-extrabold tracking-tight',
                dark ? 'text-white' : 'text-slate-950',
                className
            )}
            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
        >
            {children}
        </h2>
    );
}

function TopNav({ isOwner = false, ownerPanelHref = null, businessLabel = 'Negocios' }) {
    return (
        <nav className="fixed inset-x-0 top-0 z-50 border-b border-slate-200/70 bg-white/84 backdrop-blur-[20px]">
            <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <Link
                    href={route('public.explore')}
                    className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800"
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    <Store className="h-4 w-4" />
                    {businessLabel}
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
                        Vito Business OS
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

function MetricCard({ label, value, helper = null, dark = false }) {
    return (
        <div className={cn('rounded-[1.35rem] border px-4 py-4', dark ? 'border-white/10 bg-white/5' : 'border-slate-200 bg-white')}>
            <Eyebrow dark={dark}>{label}</Eyebrow>
            <p
                className={cn('mt-2 text-2xl font-extrabold tracking-tight', dark ? 'text-white' : 'text-slate-950')}
                style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
            >
                {value}
            </p>
            {helper ? (
                <p className={cn('mt-1 text-sm', dark ? 'text-slate-300' : 'text-slate-500')}>
                    {helper}
                </p>
            ) : null}
        </div>
    );
}

function ActionLink({ href, label, icon: Icon, variant = 'dark', external = false }) {
    const className = cn(
        'inline-flex items-center justify-center gap-2 rounded-full px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] transition',
        variant === 'dark'
            ? 'bg-slate-950 text-white hover:bg-slate-800'
            : 'border border-white/20 bg-white/10 text-white hover:bg-white/16',
    );

    if (!href) {
        return null;
    }

    const content = (
        <>
            {Icon ? <Icon className="h-4 w-4" /> : null}
            <span>{label}</span>
        </>
    );

    if (external) {
        return (
            <a
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                className={className}
                style={{ fontFamily: PUBLIC_LABEL_FONT }}
            >
                {content}
            </a>
        );
    }

    if (href.startsWith('#')) {
        return (
            <a href={href} className={className} style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                {content}
            </a>
        );
    }

    return (
        <Link href={href} className={className} style={{ fontFamily: PUBLIC_LABEL_FONT }}>
            {content}
        </Link>
    );
}

function HeroSection({
    tenant,
    profile,
    businessType,
    tenantPlan,
    cityName,
    isOpen,
    totalProductsCount,
    servicesCount,
    menuSectionsCount,
    serviceProvidersCount,
    reviewCount,
    primaryWhatsAppUrl,
    bookingUrl,
    isFollowing,
    followersCount,
    isOwner,
    ownerPanelHref,
    hasWebsite,
}) {
    const theme = BUSINESS_THEMES[businessType] ?? BUSINESS_THEMES.retail;
    const ThemeIcon = theme.icon;
    const coverImage = tenant.cover || tenant.hero_image || FALLBACK_IMAGE;

    const primaryAction = (() => {
        if (businessType === 'service' && servicesCount > 0 && bookingUrl) {
            return { href: bookingUrl, label: profile.primary_cta_label, icon: CalendarClock, external: false };
        }

        if (businessType === 'food' && primaryWhatsAppUrl) {
            return { href: primaryWhatsAppUrl, label: profile.primary_cta_label, icon: MessageCircle, external: true };
        }

        return { href: '#workspace', label: profile.primary_cta_label, icon: ArrowUpRight, external: false };
    })();

    const secondaryAction = primaryWhatsAppUrl
        ? { href: primaryWhatsAppUrl, label: profile.secondary_cta_label, icon: MessageCircle, external: true }
        : hasWebsite
            ? { href: tenant.website_url, label: 'Visitar sitio', icon: Globe, external: true }
            : null;

    return (
        <header className="px-6 pb-10 pt-24 md:pb-14" data-testid="tenant-hero">
            <div className="mx-auto max-w-7xl">
                <div className="relative overflow-hidden rounded-[2.25rem] border border-slate-200 bg-slate-950">
                    <div className="absolute inset-0">
                        <img
                            src={coverImage}
                            alt={tenant.name}
                            className="h-full w-full object-cover"
                            onError={(event) => {
                                event.target.onerror = null;
                                event.target.src = FALLBACK_IMAGE;
                            }}
                        />
                        <div className={cn('absolute inset-0 bg-gradient-to-br', theme.accentClass)} />
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.16),transparent_35%),linear-gradient(120deg,rgba(2,6,23,0.92)_18%,rgba(2,6,23,0.7)_52%,rgba(2,6,23,0.35)_100%)]" />
                    </div>

                    <div className="relative grid gap-8 px-6 py-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:p-10">
                        <div className="space-y-8">
                            <div className="flex flex-col gap-6 sm:flex-row sm:items-start">
                                <div className="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-[1.75rem] border border-white/10 bg-white/10 shadow-2xl shadow-black/20 backdrop-blur-md">
                                    {tenant.logo ? (
                                        <img
                                            src={tenant.logo}
                                            alt={`${tenant.name} logo`}
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <span
                                            className="text-3xl font-extrabold uppercase tracking-[0.12em] text-white"
                                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                        >
                                            {tenant.name?.slice(0, 2).toUpperCase()}
                                        </span>
                                    )}
                                </div>

                                <div className="flex-1 space-y-5">
                                    <div className="flex flex-wrap items-center gap-3">
                                        <span
                                            className={cn('inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em]', theme.chipClass)}
                                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                            data-testid="storefront-vertical-label"
                                        >
                                            <ThemeIcon className="h-3.5 w-3.5" />
                                            {profile.vertical_label || profile.label}
                                        </span>
                                        <span
                                            className="rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-white"
                                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                        >
                                            {tenant.category?.name || 'Negocio local'}
                                        </span>
                                        <span
                                            className="rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-200"
                                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                        >
                                            {PLAN_LABELS[tenantPlan] || tenantPlan}
                                        </span>
                                        {tenantPlan !== PLAN.SEMILLA ? (
                                            <span
                                                className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-950"
                                                style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                            >
                                                <BadgeCheck className="h-3.5 w-3.5" />
                                                Operacion lista
                                            </span>
                                        ) : null}
                                    </div>

                                    <div>
                                        <h1
                                            className="max-w-4xl text-4xl font-extrabold leading-[1.03] tracking-tight text-white md:text-6xl"
                                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                        >
                                            {tenant.name}
                                        </h1>
                                        <p className="mt-4 max-w-3xl text-lg leading-8 text-slate-200">
                                            {profile.hero_title}
                                        </p>
                                        <p className="mt-4 max-w-3xl text-base leading-8 text-slate-300">
                                            {profile.hero_copy}
                                        </p>
                                    </div>

                                    <div
                                        className="flex flex-wrap items-center gap-x-5 gap-y-3 text-sm font-semibold text-slate-200"
                                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                    >
                                        <span className="inline-flex items-center gap-2">
                                            <MapPin className="h-4 w-4 text-white" />
                                            {cityName}
                                        </span>
                                        <span className="inline-flex items-center gap-2">
                                            <span className={cn('h-2.5 w-2.5 rounded-full', isOpen ? 'bg-emerald-400' : 'bg-rose-400')} />
                                            {isOpen ? 'Abierto ahora' : 'Fuera de horario'}
                                        </span>
                                        {reviewCount > 0 ? (
                                            <span className="inline-flex items-center gap-2">
                                                <Star className="h-4 w-4 fill-white text-white" />
                                                {tenant.rating || '4.8'} ({reviewCount})
                                            </span>
                                        ) : null}
                                        {followersCount > 0 ? (
                                            <span className="inline-flex items-center gap-2">
                                                <Users className="h-4 w-4 text-white" />
                                                {followersCount} siguiendo este negocio
                                            </span>
                                        ) : null}
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3">
                                        {!isOwner ? (
                                            <FollowButton
                                                tenantId={tenant.id}
                                                isFollowing={isFollowing}
                                                followersCount={followersCount}
                                                className="!rounded-full !border-white/15 !bg-white !px-5 !py-3 !text-xs !font-semibold !uppercase !tracking-[0.16em] !text-slate-950 hover:!bg-slate-100"
                                            />
                                        ) : null}

                                        <ActionLink
                                            href={primaryAction.href}
                                            label={primaryAction.label}
                                            icon={primaryAction.icon}
                                            external={primaryAction.external}
                                        />

                                        {secondaryAction ? (
                                            <ActionLink
                                                href={secondaryAction.href}
                                                label={secondaryAction.label}
                                                icon={secondaryAction.icon}
                                                variant="ghost"
                                                external={secondaryAction.external}
                                            />
                                        ) : null}

                                        {isOwner && ownerPanelHref ? (
                                            <a
                                                href={ownerPanelHref}
                                                className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-white/16"
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

                        <aside className="rounded-[1.8rem] border border-white/10 bg-white/10 p-6 shadow-2xl shadow-slate-950/20 backdrop-blur-md">
                            <Eyebrow dark>Modo de atencion</Eyebrow>
                            <SectionTitle dark className="mt-3 text-2xl">
                                {profile.offer_title}
                            </SectionTitle>
                            <p className="mt-3 text-sm leading-7 text-slate-200">
                                {profile.offer_copy}
                            </p>

                            {Array.isArray(profile.trust_points) && profile.trust_points.length > 0 ? (
                                <div className="mt-5 flex flex-wrap gap-2">
                                    {profile.trust_points.slice(0, 3).map((point) => (
                                        <span
                                            key={point}
                                            className="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-white"
                                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                        >
                                            {point}
                                        </span>
                                    ))}
                                </div>
                            ) : null}

                            <div className="mt-6 grid grid-cols-2 gap-3">
                                <MetricCard
                                    label={profile.mobile_tab_label}
                                    value={businessType === 'service' ? servicesCount : totalProductsCount}
                                    helper={businessType === 'service' ? 'servicios visibles' : 'items visibles'}
                                    dark
                                />
                                <MetricCard label="Resenas" value={reviewCount} helper="feedback disponible" dark />
                                <MetricCard label="Secciones" value={menuSectionsCount} helper="bloques activos" dark />
                                <MetricCard label="Equipo" value={serviceProvidersCount} helper="personas visibles" dark />
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </header>
    );
}

function PreviewBanner({ previewReason, ownerPanelHref }) {
    return (
        <section className="px-6 pb-6">
            <div className="mx-auto flex max-w-7xl flex-col gap-4 rounded-[1.75rem] bg-slate-950 px-6 py-5 text-white md:flex-row md:items-center md:justify-between">
                <div>
                    <Eyebrow dark>Vista previa privada</Eyebrow>
                    <p className="mt-2 text-sm leading-7 text-slate-300">
                        {previewReason || 'Este negocio todavia no esta visible para visitantes.'}
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
        </section>
    );
}

function FocusStrip({ businessType, services, menuSections, products, bookingUrl, primaryWhatsAppUrl, profile }) {
    const configuredCards = Array.isArray(profile.focus_cards) ? profile.focus_cards : [];
    const cards = configuredCards.length > 0
        ? configuredCards.map((card) => {
            const actionHref = resolveVerticalActionTarget(card.cta_target, primaryWhatsAppUrl, bookingUrl) || '#workspace';

            return {
                title: card.title,
                copy: card.copy,
                actionLabel: card.cta_label,
                actionHref,
                external: typeof actionHref === 'string' && actionHref.startsWith('http'),
            };
        })
        : (() => {
        switch (businessType) {
            case 'food':
                return [
                    {
                        title: 'Menu organizado',
                        copy: menuSections.length > 0
                            ? `${menuSections.length} secciones listas para que el cliente encuentre platos, combos o bebidas sin perderse.`
                            : 'Agrupa entradas, platos fuertes, bebidas y postres para vender con mas claridad.',
                        actionLabel: 'Ver menu',
                        actionHref: '#catalogo',
                    },
                    {
                        title: 'Pedidos rapidos',
                        copy: 'Combina vitrina publica con contacto inmediato para captar pedidos desde movil y escritorio.',
                        actionLabel: primaryWhatsAppUrl ? 'Pedir por WhatsApp' : 'Explorar',
                        actionHref: primaryWhatsAppUrl || '#workspace',
                        external: Boolean(primaryWhatsAppUrl),
                    },
                    {
                        title: 'Promocion del dia',
                        copy: 'Usa fotos claras, secciones activas y CTA visibles para empujar tus platos estrella.',
                        actionLabel: 'Abrir catalogo',
                        actionHref: '#catalogo',
                    },
                ];
            case 'service':
                return [
                    {
                        title: 'Servicios claros',
                        copy: services.length > 0
                            ? `${services.length} servicios visibles con precio y duracion para que el cliente decida antes de escribirte.`
                            : 'Presenta paquetes, sesiones o consultas de forma profesional y facil de comparar.',
                        actionLabel: 'Ver servicios',
                        actionHref: '#servicios',
                    },
                    {
                        title: 'Reserva o consulta',
                        copy: 'Abogados, consultorias, clinicas y belleza necesitan confianza, agenda y contacto sin friccion.',
                        actionLabel: bookingUrl ? 'Reservar' : 'Consultar',
                        actionHref: bookingUrl || '#servicios',
                    },
                    {
                        title: 'Credibilidad operativa',
                        copy: 'Horarios, ubicacion, equipo y resenas quedan visibles para reducir dudas antes de cotizar.',
                        actionLabel: 'Ver informacion',
                        actionHref: '#informacion',
                    },
                ];
            case 'hybrid':
                return [
                    {
                        title: 'Oferta mixta',
                        copy: 'Un mismo negocio puede vender productos, atender servicios y sostener seguimiento comercial sin romper la experiencia.',
                        actionLabel: 'Ver ofertas',
                        actionHref: '#ofertas',
                    },
                    {
                        title: 'Catalogo + agenda',
                        copy: 'Ideal para tiendas con soporte tecnico, talleres, salud estetica o negocios con upsells hibridos.',
                        actionLabel: bookingUrl ? 'Reservar o consultar' : 'Hablar por WhatsApp',
                        actionHref: bookingUrl || primaryWhatsAppUrl || '#workspace',
                        external: Boolean(primaryWhatsAppUrl && !bookingUrl),
                    },
                    {
                        title: 'Experiencia coherente',
                        copy: 'Integra productos, servicios y CTA sin obligar al cliente a adivinar como comprar o reservar.',
                        actionLabel: profile.catalog_title,
                        actionHref: '#catalogo',
                    },
                ];
            default:
                return [
                    {
                        title: 'Vitrina fuerte',
                        copy: products.length > 0
                            ? `${products.length} productos visibles para descubrir, cotizar o comprar con menos pasos.`
                            : 'Arma un catalogo claro para celulares, ferreteria, moda, hogar o cualquier tienda especializada.',
                        actionLabel: 'Ver catalogo',
                        actionHref: '#catalogo',
                    },
                    {
                        title: 'Contacto directo',
                        copy: 'El cliente puede avanzar rapido desde el micrositio a WhatsApp, llamada o carrito segun tu plan.',
                        actionLabel: primaryWhatsAppUrl ? 'Hablar por WhatsApp' : 'Ver negocio',
                        actionHref: primaryWhatsAppUrl || '#workspace',
                        external: Boolean(primaryWhatsAppUrl),
                    },
                    {
                        title: 'Mas conversion',
                        copy: 'Fotos, precios y copy comercial trabajan juntos para que la visita termine en una accion concreta.',
                        actionLabel: 'Explorar productos',
                        actionHref: '#catalogo',
                    },
                ];
        }
    })();

    return (
        <section className="px-6 pb-8">
            <div className="mx-auto grid max-w-7xl gap-4 lg:grid-cols-3">
                {cards.map((card) => (
                    <article key={card.title} className="rounded-[1.7rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5">
                        <Eyebrow>{profile.offer_title}</Eyebrow>
                        <h3
                            className="mt-3 text-2xl font-bold tracking-tight text-slate-950"
                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                        >
                            {card.title}
                        </h3>
                        <p className="mt-3 text-sm leading-7 text-slate-600">
                            {card.copy}
                        </p>
                        <div className="mt-5">
                            {card.external ? (
                                <a
                                    href={card.actionHref}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-2 text-sm font-semibold text-slate-950 transition hover:text-slate-700"
                                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                >
                                    {card.actionLabel}
                                    <ArrowUpRight className="h-4 w-4" />
                                </a>
                            ) : (
                                <a
                                    href={card.actionHref}
                                    className="inline-flex items-center gap-2 text-sm font-semibold text-slate-950 transition hover:text-slate-700"
                                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                >
                                    {card.actionLabel}
                                    <ArrowUpRight className="h-4 w-4" />
                                </a>
                            )}
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}

function resolveVerticalActionTarget(target, primaryWhatsAppUrl, bookingUrl) {
    if (target === 'whatsapp') {
        return primaryWhatsAppUrl;
    }

    if (target === 'booking') {
        return bookingUrl;
    }

    return target;
}

function buildVerticalSignals({
    verticalKey,
    businessType,
    productsCount,
    servicesCount,
    menuSectionsCount,
    serviceProvidersCount,
    hasBooking,
    hasWhatsApp,
    isOpen,
}) {
    switch (verticalKey || businessType) {
        case 'casual_restaurant':
            return [
                { label: 'Combos', value: menuSectionsCount || 'Listo', helper: 'secciones activas' },
                { label: 'Ticket', value: productsCount > 2 ? 'Upsell' : 'Base', helper: 'platos para rotar' },
                { label: 'Pedidos', value: hasWhatsApp ? 'Directo' : 'Catalogo', helper: 'canal de cierre' },
            ];
        case 'coffee_shop':
            return [
                { label: 'Carta', value: menuSectionsCount || 'Lista', helper: 'bloques visibles' },
                { label: 'Brunch', value: productsCount, helper: 'items destacados' },
                { label: 'Pickup', value: hasWhatsApp ? 'Activo' : 'Manual', helper: 'pedido rapido' },
            ];
        case 'restaurant':
            return [
                { label: 'Menu', value: menuSectionsCount || 'Listo', helper: 'secciones activas' },
                { label: 'Platos', value: productsCount, helper: 'items visibles' },
                { label: 'Pedidos', value: hasWhatsApp ? 'Directo' : 'Catalogo', helper: 'canal rapido' },
            ];
        case 'premium_phone_store':
            return [
                { label: 'Flagship', value: productsCount > 0 ? 'Activo' : 'Base', helper: 'equipos premium' },
                { label: 'Bundles', value: productsCount > 1 ? 'Listos' : 'Base', helper: 'upsell de margen' },
                { label: 'Soporte', value: hasWhatsApp ? 'Listo' : 'Manual', helper: 'postventa visible' },
            ];
        case 'cellphone_store':
            return [
                { label: 'Equipos', value: productsCount, helper: 'catalogo visible' },
                { label: 'Bundles', value: productsCount > 1 ? 'Activos' : 'Base', helper: 'upsell listo' },
                { label: 'Soporte', value: hasWhatsApp ? 'Listo' : 'Manual', helper: 'canal posventa' },
            ];
        case 'hardware_store':
            return [
                { label: 'Materiales', value: productsCount, helper: 'catalogo activo' },
                { label: 'Cotiza', value: hasWhatsApp ? 'Rapido' : 'Manual', helper: 'cierre comercial' },
                { label: 'Operacion', value: isOpen ? 'Hoy' : 'Cerrado', helper: 'tienda disponible' },
            ];
        case 'dental_clinic':
            return [
                { label: 'Agenda', value: hasBooking ? 'Activa' : 'Manual', helper: 'evaluaciones visibles' },
                { label: 'Tratamientos', value: servicesCount, helper: 'procedimientos claros' },
                { label: 'Equipo', value: serviceProvidersCount || 'Staff', helper: 'especialistas visibles' },
            ];
        case 'clinic':
            return [
                { label: 'Agenda', value: hasBooking ? 'Activa' : 'Manual', helper: 'recepcion visible' },
                { label: 'Servicios', value: servicesCount, helper: 'consultas visibles' },
                { label: 'Estado', value: isOpen ? 'Hoy' : 'Cerrado', helper: 'atencion del negocio' },
            ];
        case 'corporate_law_firm':
            return [
                { label: 'Empresas', value: 'B2B', helper: 'foco corporativo' },
                { label: 'Consultas', value: servicesCount, helper: 'areas visibles' },
                { label: 'Intake', value: hasBooking ? 'Agenda' : 'Contacto', helper: 'primer filtro' },
            ];
        case 'law_firm':
            return [
                { label: 'Consultas', value: servicesCount, helper: 'servicios legales' },
                { label: 'Despacho', value: hasBooking ? 'Agenda' : 'Contacto', helper: 'primer filtro' },
                { label: 'Atencion', value: hasWhatsApp ? 'Directa' : 'Manual', helper: 'respuesta inicial' },
            ];
        case 'financial_consultancy':
            return [
                { label: 'Discovery', value: hasBooking ? 'Activa' : 'Lead', helper: 'reunion inicial' },
                { label: 'Servicios', value: servicesCount, helper: 'frentes financieros' },
                { label: 'Seguimiento', value: hasWhatsApp ? 'Activo' : 'Manual', helper: 'canal de avance' },
            ];
        case 'consultancy':
            return [
                { label: 'Discovery', value: hasBooking ? 'Activa' : 'Lead', helper: 'entrada consultiva' },
                { label: 'Servicios', value: servicesCount, helper: 'paquetes visibles' },
                { label: 'Funnel', value: hasWhatsApp ? 'WhatsApp' : 'Manual', helper: 'seguimiento comercial' },
            ];
        case 'auto_parts_store':
            return [
                { label: 'Repuestos', value: productsCount, helper: 'piezas visibles' },
                { label: 'Diagnostico', value: servicesCount > 0 ? 'Listo' : 'Manual', helper: 'soporte tecnico' },
                { label: 'Ingreso', value: hasWhatsApp ? 'Directo' : 'Manual', helper: 'consulta inicial' },
            ];
        case 'workshop':
            return [
                { label: 'Diagnosticos', value: servicesCount, helper: 'servicios tecnicos' },
                { label: 'Repuestos', value: productsCount, helper: 'catalogo visible' },
                { label: 'Ingreso', value: hasWhatsApp ? 'Directo' : 'Manual', helper: 'primer contacto' },
            ];
        case 'premium_barbershop':
            return [
                { label: 'Agenda', value: hasBooking ? 'Activa' : 'Manual', helper: 'slots premium' },
                { label: 'Rituales', value: servicesCount, helper: 'servicios visibles' },
                { label: 'Equipo', value: serviceProvidersCount, helper: 'barberos visibles' },
            ];
        case 'spa_studio':
            return [
                { label: 'Rituales', value: servicesCount, helper: 'cabina y bienestar' },
                { label: 'Reserva', value: hasBooking ? 'Activa' : 'Manual', helper: 'agenda abierta' },
                { label: 'Atencion', value: hasWhatsApp ? 'Directa' : 'Manual', helper: 'canal de apoyo' },
            ];
        case 'barbershop':
            return [
                { label: 'Agenda', value: hasBooking ? 'Activa' : 'Manual', helper: 'reservas abiertas' },
                { label: 'Servicios', value: servicesCount, helper: 'bloques visibles' },
                { label: 'Equipo', value: serviceProvidersCount, helper: 'especialistas visibles' },
            ];
        case 'hybrid':
            return [
                { label: 'Servicios', value: servicesCount, helper: 'frente consultivo' },
                { label: 'Catalogo', value: productsCount, helper: 'items visibles' },
                { label: 'Canal', value: hasBooking ? 'Reserva' : hasWhatsApp ? 'WhatsApp' : 'Manual', helper: 'entrada principal' },
            ];
        case 'service':
            return [
                { label: 'Servicios', value: servicesCount, helper: 'bloques visibles' },
                { label: 'Equipo', value: serviceProvidersCount, helper: 'personas visibles' },
                { label: 'Canal', value: hasBooking ? 'Reserva' : hasWhatsApp ? 'WhatsApp' : 'Manual', helper: 'entrada principal' },
            ];
        default:
            return [
                { label: 'Catalogo', value: productsCount, helper: 'items visibles' },
                { label: 'Canal', value: hasWhatsApp ? 'WhatsApp' : 'Manual', helper: 'contacto principal' },
                { label: 'Estado', value: isOpen ? 'Abierto' : 'Cerrado', helper: 'situacion actual' },
            ];
    }
}

function VerticalPlaybookSection({
    profile,
    businessType,
    productsCount,
    servicesCount,
    menuSectionsCount,
    serviceProvidersCount,
    primaryWhatsAppUrl,
    bookingUrl,
    isOpen,
}) {
    const journeySteps = Array.isArray(profile.journey_steps) ? profile.journey_steps : [];
    const growthCards = Array.isArray(profile.growth_cards) ? profile.growth_cards : [];
    const signals = buildVerticalSignals({
        verticalKey: profile.vertical_key,
        businessType,
        productsCount,
        servicesCount,
        menuSectionsCount,
        serviceProvidersCount,
        hasBooking: Boolean(bookingUrl),
        hasWhatsApp: Boolean(primaryWhatsAppUrl),
        isOpen,
    });

    if (journeySteps.length === 0 && growthCards.length === 0 && signals.length === 0) {
        return null;
    }

    return (
        <section className="px-6 pb-10" data-testid="vertical-playbook">
            <div className="mx-auto grid max-w-7xl gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                <article className="overflow-hidden rounded-[2rem] bg-slate-950 p-8 text-white shadow-2xl shadow-slate-950/10" data-testid="vertical-journey">
                    <Eyebrow dark>Playbook del vertical</Eyebrow>
                    <SectionTitle dark className="mt-3 text-4xl">
                        {profile.journey_title || 'Como convierte este negocio'}
                    </SectionTitle>
                    <p className="mt-4 max-w-3xl text-base leading-8 text-slate-300">
                        {profile.journey_copy || 'La vitrina publica ya aterriza mejor el camino desde el interes hasta la accion.'}
                    </p>

                    {journeySteps.length > 0 ? (
                        <div className="mt-8 grid gap-4 lg:grid-cols-3">
                            {journeySteps.map((step) => (
                                <article key={step.title} className="rounded-[1.5rem] border border-white/10 bg-white/5 p-5" data-testid="vertical-journey-step">
                                    <p
                                        className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-300"
                                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                    >
                                        {step.eyebrow || 'Paso'}
                                    </p>
                                    <h3
                                        className="mt-3 text-xl font-bold tracking-tight text-white"
                                        style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                    >
                                        {step.title}
                                    </h3>
                                    <p className="mt-3 text-sm leading-7 text-slate-300">{step.copy}</p>
                                </article>
                            ))}
                        </div>
                    ) : null}
                </article>

                <div className="space-y-4">
                    {signals.length > 0 ? (
                        <div className="grid gap-4 sm:grid-cols-3 xl:grid-cols-1" data-testid="vertical-signal-grid">
                            {signals.map((signal) => (
                                <article key={signal.label} className="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5">
                                    <Eyebrow>{signal.label}</Eyebrow>
                                    <p
                                        className="mt-3 text-3xl font-extrabold tracking-tight text-slate-950"
                                        style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                    >
                                        {signal.value}
                                    </p>
                                    <p className="mt-2 text-sm leading-7 text-slate-600">{signal.helper}</p>
                                </article>
                            ))}
                        </div>
                    ) : null}

                    {growthCards.length > 0 ? (
                        <article className="rounded-[1.9rem] border border-slate-200 bg-slate-50 p-6">
                            <Eyebrow>Crecimiento</Eyebrow>
                            <SectionTitle className="mt-3 text-3xl">
                                {profile.growth_title || 'Palancas de crecimiento'}
                            </SectionTitle>
                            <p className="mt-3 text-sm leading-7 text-slate-600">
                                {profile.growth_copy || 'Cada vertical necesita una narrativa distinta para vender mejor.'}
                            </p>

                            <div className="mt-6 space-y-4">
                                {growthCards.map((card) => {
                                    const href = resolveVerticalActionTarget(card.cta_target, primaryWhatsAppUrl, bookingUrl);
                                    const isExternal = typeof href === 'string' && href.startsWith('http');

                                    return (
                                        <article key={card.title} className="rounded-[1.5rem] border border-slate-200 bg-white p-5" data-testid="growth-playbook-card">
                                            <p
                                                className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500"
                                                style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                            >
                                                {card.eyebrow || 'Bloque'}
                                            </p>
                                            <h3
                                                className="mt-3 text-xl font-bold tracking-tight text-slate-950"
                                                style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                            >
                                                {card.title}
                                            </h3>
                                            <p className="mt-3 text-sm leading-7 text-slate-600">{card.copy}</p>

                                            {href ? (
                                                <div className="mt-5">
                                                    {isExternal ? (
                                                        <a
                                                            href={href}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-2 text-sm font-semibold text-slate-950 transition hover:text-slate-700"
                                                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                                        >
                                                            {card.cta_label}
                                                            <ArrowUpRight className="h-4 w-4" />
                                                        </a>
                                                    ) : (
                                                        <a
                                                            href={href}
                                                            className="inline-flex items-center gap-2 text-sm font-semibold text-slate-950 transition hover:text-slate-700"
                                                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                                        >
                                                            {card.cta_label}
                                                            <ArrowUpRight className="h-4 w-4" />
                                                        </a>
                                                    )}
                                                </div>
                                            ) : null}
                                        </article>
                                    );
                                })}
                            </div>
                        </article>
                    ) : null}
                </div>
            </div>
        </section>
    );
}

function VerticalShowcaseSection({ profile, primaryWhatsAppUrl, bookingUrl }) {
    const blocks = Array.isArray(profile.specialized_blocks) ? profile.specialized_blocks : [];
    const trustPoints = Array.isArray(profile.trust_points) ? profile.trust_points : [];

    if (blocks.length === 0 && trustPoints.length === 0) {
        return null;
    }

    return (
        <section className="px-6 pb-8" data-testid="vertical-showcase">
            <div className="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
                <aside className="rounded-[1.8rem] border border-slate-200 bg-slate-50 p-6">
                    <Eyebrow>Vertical especializada</Eyebrow>
                    <SectionTitle className="mt-3 text-3xl">
                        {profile.vertical_label || profile.label}
                    </SectionTitle>
                    <p className="mt-3 text-sm leading-7 text-slate-600">
                        Esta vitrina ya no habla solo del tipo de negocio: aterriza la propuesta para una industria concreta y hace mas claro como comprar, reservar o consultar.
                    </p>

                    {trustPoints.length > 0 ? (
                        <div className="mt-6 flex flex-wrap gap-3">
                            {trustPoints.map((point) => (
                                <span
                                    key={point}
                                    className="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-700"
                                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                >
                                    {point}
                                </span>
                            ))}
                        </div>
                    ) : null}
                </aside>

                <div className="grid gap-4 xl:grid-cols-2">
                    {blocks.map((block) => {
                        const href = resolveVerticalActionTarget(block.cta_target, primaryWhatsAppUrl, bookingUrl);
                        const isExternal = typeof href === 'string' && href.startsWith('http');

                        return (
                            <article key={block.title} className="rounded-[1.8rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5" data-testid="vertical-block-card">
                                <Eyebrow>{profile.vertical_label || profile.label}</Eyebrow>
                                <h3
                                    className="mt-3 text-2xl font-bold tracking-tight text-slate-950"
                                    style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                >
                                    {block.title}
                                </h3>
                                <p className="mt-3 text-sm leading-7 text-slate-600">
                                    {block.subtitle}
                                </p>

                                {Array.isArray(block.bullets) && block.bullets.length > 0 ? (
                                    <ul className="mt-5 space-y-3 text-sm leading-7 text-slate-600">
                                        {block.bullets.map((bullet) => (
                                            <li key={bullet} className="flex items-start gap-3">
                                                <span className="mt-2 h-2.5 w-2.5 rounded-full bg-slate-950" />
                                                <span>{bullet}</span>
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}

                                {href ? (
                                    <div className="mt-6">
                                        {isExternal ? (
                                            <a
                                                href={href}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-2 text-sm font-semibold text-slate-950 transition hover:text-slate-700"
                                                style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                            >
                                                {block.cta_label}
                                                <ArrowUpRight className="h-4 w-4" />
                                            </a>
                                        ) : (
                                            <a
                                                href={href}
                                                className="inline-flex items-center gap-2 text-sm font-semibold text-slate-950 transition hover:text-slate-700"
                                                style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                            >
                                                {block.cta_label}
                                                <ArrowUpRight className="h-4 w-4" />
                                            </a>
                                        )}
                                    </div>
                                ) : null}
                            </article>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}

function WorkspaceTabs({ tabs, activeTab, onChange }) {
    return (
        <div className="sticky top-16 z-30 border-b border-slate-200/70 bg-white/92 backdrop-blur-xl" data-testid="storefront-tabs">
            <div className="mx-auto max-w-7xl px-6 py-4">
                <div className="flex flex-wrap gap-2 rounded-full bg-slate-100 p-1">
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => onChange(tab.id)}
                            className={cn(
                                'rounded-full px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.18em] transition',
                                activeTab === tab.id
                                    ? 'bg-white text-slate-950 shadow-sm shadow-slate-950/5'
                                    : 'text-slate-500 hover:text-slate-950'
                            )}
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}

function SearchToolbar({
    title,
    copy,
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
                    <Eyebrow>{title}</Eyebrow>
                    <SectionTitle className="mt-3 text-3xl">{title}</SectionTitle>
                    <p className="mt-3 text-base leading-8 text-slate-600">{copy}</p>
                </div>

                <form onSubmit={handleSearch} className="w-full max-w-md">
                    <label className="sr-only" htmlFor="catalog-search">
                        Buscar
                    </label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            id="catalog-search"
                            value={searchQuery}
                            onChange={(event) => setSearchQuery(event.target.value)}
                            placeholder="Busca productos, platos o palabras clave"
                            className="w-full rounded-full border border-slate-200 bg-white px-12 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-slate-300 focus:ring-2 focus:ring-slate-200"
                        />
                    </div>
                </form>
            </div>

            <div className="mt-6 flex flex-wrap gap-3">
                <span className="rounded-full bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                    {currentCount} visibles ahora
                </span>
                <span className="rounded-full bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                    {visibleTotal} disponibles
                </span>
                {hasHiddenProducts ? (
                    <span className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                        <Lock className="h-3.5 w-3.5" />
                        {hiddenProductsCount} ocultos por plan o visibilidad
                    </span>
                ) : null}
            </div>
        </section>
    );
}

function MenuOverviewSection({ menuSections = [] }) {
    if (menuSections.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-3 md:grid-cols-3">
            {menuSections.map((section) => (
                <div key={section.id} className="rounded-[1.4rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5">
                    <Eyebrow>Seccion activa</Eyebrow>
                    <h3
                        className="mt-3 text-xl font-bold tracking-tight text-slate-950"
                        style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                    >
                        {section.name}
                    </h3>
                    <p className="mt-2 text-sm leading-7 text-slate-600">
                        {section.products_count} productos visibles en esta parte del menu.
                    </p>
                </div>
            ))}
        </div>
    );
}

function StorefrontProductCard({ product, tenant, tenantPlan }) {
    return (
        <article className="overflow-hidden rounded-[1.7rem] border border-slate-200 bg-white shadow-sm shadow-slate-950/5 transition hover:-translate-y-1 hover:shadow-lg hover:shadow-slate-950/10">
            <div className="relative aspect-[4/3] overflow-hidden bg-slate-100">
                {product.image ? (
                    <img
                        src={product.image}
                        alt={product.name}
                        className="h-full w-full object-cover transition duration-500 hover:scale-105"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <ShoppingBag className="h-12 w-12 text-slate-300" />
                    </div>
                )}

                <div className="absolute right-4 top-4 rounded-full bg-black/65 px-3 py-1.5 text-sm font-bold text-white backdrop-blur-sm">
                    {product.formatted_price || `L. ${product.price}`}
                </div>
            </div>

            <div className="space-y-4 p-5">
                <div>
                    <h3
                        className="text-xl font-bold tracking-tight text-slate-950"
                        style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                    >
                        {product.name}
                    </h3>
                    <p className="mt-2 min-h-[3.5rem] text-sm leading-7 text-slate-600">
                        {product.description || 'Disponible para cotizar o comprar desde este micrositio.'}
                    </p>
                </div>

                <ProductActionArea
                    product={product}
                    tenantId={tenant.id}
                    tenantPlan={tenantPlan}
                    tenantWhatsapp={tenant.whatsapp || tenant.whatsapp_number}
                    tenantName={tenant.name}
                    tenantSlug={tenant.slug}
                    className="w-full"
                />
            </div>
        </article>
    );
}

function PaginationBar({ pagination = [] }) {
    if (!Array.isArray(pagination) || pagination.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {pagination.map((link, index) => {
                const label = stripPaginationLabel(link.label);
                if (!label) {
                    return null;
                }

                const className = cn(
                    'inline-flex min-w-[2.75rem] items-center justify-center rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] transition',
                    link.active
                        ? 'bg-slate-950 text-white'
                        : 'border border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-950'
                );

                return link.url ? (
                    <Link
                        key={`${label}-${index}`}
                        href={link.url}
                        preserveScroll
                        preserveState
                        className={className}
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        {label}
                    </Link>
                ) : (
                    <span
                        key={`${label}-${index}`}
                        className="inline-flex min-w-[2.75rem] items-center justify-center rounded-full border border-slate-100 bg-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400"
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        {label}
                    </span>
                );
            })}
        </div>
    );
}

function ProductPanel({
    businessType,
    profile,
    panelTitle,
    panelCopy,
    searchQuery,
    setSearchQuery,
    handleSearch,
    products,
    pagination,
    tenant,
    tenantPlan,
    hiddenProductsCount,
    hasHiddenProducts,
    visibleTotal,
    menuSections,
}) {
    const emptyMessage = businessType === 'service'
        ? 'Este negocio prioriza servicios. Si tambien vende complementos, apareceran aqui.'
        : businessType === 'food'
            ? 'Todavia no hay platos visibles en el menu publico.'
            : 'Todavia no hay productos visibles en esta vitrina.';

    return (
        <div className="space-y-8" data-testid="product-panel">
            <SearchToolbar
                title={panelTitle || profile.catalog_panel_title || profile.catalog_title}
                copy={panelCopy || profile.catalog_panel_copy || profile.catalog_copy}
                searchQuery={searchQuery}
                setSearchQuery={setSearchQuery}
                handleSearch={handleSearch}
                visibleTotal={visibleTotal}
                currentCount={products.length}
                hiddenProductsCount={hiddenProductsCount}
                hasHiddenProducts={hasHiddenProducts}
            />

            {(businessType === 'food' || businessType === 'hybrid') && menuSections.length > 0 ? (
                <MenuOverviewSection menuSections={menuSections} />
            ) : null}

            {products.length > 0 ? (
                <>
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {products.map((product) => (
                            <StorefrontProductCard
                                key={product.id}
                                product={product}
                                tenant={tenant}
                                tenantPlan={tenantPlan}
                            />
                        ))}
                    </div>

                    <PaginationBar pagination={pagination} />
                </>
            ) : (
                <div className="rounded-[1.8rem] border border-dashed border-slate-300 bg-slate-50 py-16 text-center">
                    <ShoppingBag className="mx-auto mb-4 h-14 w-14 text-slate-300" />
                    <p className="text-lg font-semibold text-slate-700">Sin catalogo publico por ahora</p>
                    <p className="mt-2 text-sm leading-7 text-slate-500">{emptyMessage}</p>
                </div>
            )}
        </div>
    );
}

function ServicePanel({ services, bookingUrl, serviceProvidersCount, primaryWhatsAppUrl, profile }) {
    return (
        <section id="servicios" className="space-y-8" data-testid="service-panel">
            <div className="rounded-[1.9rem] bg-slate-50 p-6 md:p-8">
                <Eyebrow>Servicios</Eyebrow>
                <SectionTitle className="mt-3 text-3xl">
                    {profile?.service_panel_title || 'Reserva, cotiza o consulta con claridad'}
                </SectionTitle>
                <p className="mt-3 max-w-3xl text-base leading-8 text-slate-600">
                    {profile?.service_panel_copy || 'Servicios visibles, duracion, precio y una forma directa de avanzar a cita o conversacion.'}
                </p>
                <div className="mt-6 flex flex-wrap gap-3">
                    <span className="rounded-full bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                        {services.length} servicios activos
                    </span>
                    <span className="rounded-full bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                        {serviceProvidersCount} personas visibles
                    </span>
                </div>
            </div>

            {services.length > 0 ? (
                <div className="grid gap-5 xl:grid-cols-2">
                    {services.map((service) => (
                        <article key={service.id} className="rounded-[1.7rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5">
                            <Eyebrow>Servicio destacado</Eyebrow>
                            <div className="mt-3 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h3
                                        className="text-2xl font-bold tracking-tight text-slate-950"
                                        style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                    >
                                        {service.name}
                                    </h3>
                                    <p className="mt-3 text-sm leading-7 text-slate-600">
                                        {service.description || 'Atencion profesional lista para reservar o cotizar.'}
                                    </p>
                                </div>

                                <div className="rounded-[1.25rem] bg-slate-950 px-4 py-3 text-white">
                                    <Eyebrow dark>Precio</Eyebrow>
                                    <p className="mt-2 text-xl font-bold" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                        {service.formatted_price}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-300">
                                        {service.duration_formatted || `${service.duration_minutes} min`}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-5 flex flex-wrap gap-3">
                                {bookingUrl ? (
                                    <Link
                                        href={`${bookingUrl}?service=${service.id}`}
                                        className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-white transition hover:bg-slate-800"
                                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                    >
                                        <CalendarClock className="h-4 w-4" />
                                        Reservar
                                    </Link>
                                ) : null}

                                {primaryWhatsAppUrl ? (
                                    <a
                                        href={primaryWhatsAppUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-950 transition hover:border-slate-300 hover:bg-slate-50"
                                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                    >
                                        <MessageCircle className="h-4 w-4" />
                                        Consultar
                                    </a>
                                ) : null}
                            </div>
                        </article>
                    ))}
                </div>
            ) : (
                <div className="rounded-[1.8rem] border border-dashed border-slate-300 bg-slate-50 py-16 text-center">
                    <CalendarClock className="mx-auto mb-4 h-14 w-14 text-slate-300" />
                    <p className="text-lg font-semibold text-slate-700">Todavia no hay servicios visibles</p>
                    <p className="mt-2 text-sm leading-7 text-slate-500">
                        Cuando el negocio publique sus servicios, esta seccion se convertira en su frente de reservas y consultas.
                    </p>
                </div>
            )}
        </section>
    );
}

function OffersPanel({ businessType, services, menuSections, products, bookingUrl, primaryWhatsAppUrl }) {
    const cards = [
        {
            title: businessType === 'service' ? 'Servicios visibles' : 'Productos visibles',
            value: businessType === 'service' ? services.length : products.length,
            copy: businessType === 'service'
                ? 'Presentacion clara para que el cliente entienda el alcance antes de reservar.'
                : 'Catalogo publico listo para mover ventas, consultas o pedidos.',
        },
        {
            title: 'Secciones activas',
            value: menuSections.length,
            copy: 'Util para restaurantes o negocios que necesitan organizar su oferta por bloques.',
        },
        {
            title: 'Agenda y contacto',
            value: bookingUrl ? 'Listo' : primaryWhatsAppUrl ? 'WhatsApp' : 'Manual',
            copy: 'Canales directos para convertir interes en reserva, pedido o conversacion.',
        },
    ];

    return (
        <section id="ofertas" className="space-y-8">
            <div className="grid gap-4 lg:grid-cols-3">
                {cards.map((card) => (
                    <article key={card.title} className="rounded-[1.7rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5">
                        <Eyebrow>Oferta principal</Eyebrow>
                        <h3
                            className="mt-3 text-xl font-bold tracking-tight text-slate-950"
                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                        >
                            {card.title}
                        </h3>
                        <p
                            className="mt-4 text-3xl font-extrabold tracking-tight text-slate-950"
                            style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                        >
                            {card.value}
                        </p>
                        <p className="mt-3 text-sm leading-7 text-slate-600">{card.copy}</p>
                    </article>
                ))}
            </div>

            {services.length > 0 ? (
                <div className="rounded-[1.8rem] border border-slate-200 bg-slate-50 p-6">
                    <Eyebrow>Servicios visibles</Eyebrow>
                    <SectionTitle className="mt-3 text-2xl">La parte consultiva del negocio</SectionTitle>
                    <div className="mt-6 grid gap-4 md:grid-cols-2">
                        {services.slice(0, 4).map((service) => (
                            <div key={service.id} className="rounded-[1.35rem] border border-slate-200 bg-white p-5">
                                <h3
                                    className="text-lg font-bold tracking-tight text-slate-950"
                                    style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                                >
                                    {service.name}
                                </h3>
                                <p className="mt-2 text-sm leading-7 text-slate-600">{service.description || 'Servicio profesional disponible.'}</p>
                                <p className="mt-4 text-sm font-semibold text-slate-950">
                                    {service.formatted_price}  -  {service.duration_formatted || `${service.duration_minutes} min`}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            ) : null}
        </section>
    );
}

function InfoPanel({ tenant, isOpen, openingHours = [] }) {
    return (
        <section id="informacion" className="grid gap-8 lg:grid-cols-[minmax(0,1.65fr)_360px]">
            <article className="rounded-[1.9rem] bg-slate-50 p-8 md:p-10">
                <Eyebrow>Presentacion</Eyebrow>
                <SectionTitle className="mt-3 text-3xl">Sobre {tenant.name}</SectionTitle>
                <p className="mt-6 whitespace-pre-line text-base leading-8 text-slate-600">
                    {tenant.about || tenant.description || 'Bienvenido a este negocio dentro de Vito Business OS.'}
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
                                <p className="mt-1 leading-7">{tenant.address || tenant.location?.address || 'No especificada'}</p>
                            </div>
                        </div>

                        <div className="flex items-start gap-4">
                            <Clock3 className="mt-1 h-4 w-4 text-slate-950" />
                            <div>
                                <p className="font-semibold text-slate-950">Estado</p>
                                <p className="mt-1 leading-7">{isOpen ? 'Abierto ahora' : 'Cerrado en este momento'}</p>
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
                            {openingHours.map((slot) => (
                                <div key={`${slot.day_name}-${slot.day_of_week}`} className="flex items-center justify-between gap-4 text-sm">
                                    <span className="font-semibold text-white">
                                        {slot.day_name}
                                    </span>
                                    <span className="text-slate-300">
                                        {slot.formatted}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </article>
                ) : null}
            </div>
        </section>
    );
}

function ReviewsPanel({ tenant }) {
    return (
        <section className="rounded-[1.9rem] bg-slate-50 p-6 md:p-8">
            <ReviewSection tenant={tenant} reviews={tenant.reviews || []} />
        </section>
    );
}

function MicrositeFooter({ tenant, profile, primaryWhatsAppUrl, tenantPlan }) {
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
                        {profile.footer_copy}
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
                        className={cn(
                            'inline-flex items-center gap-2 rounded-full border border-slate-200 px-5 py-3 text-xs font-semibold uppercase tracking-[0.16em] transition hover:border-slate-300 hover:bg-slate-100',
                            tenantPlan === PLAN.SEMILLA ? 'text-slate-950' : 'text-slate-700'
                        )}
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

function MobileDock({ primaryLabel, primaryHref, tenant, primaryWhatsAppUrl, bookingUrl }) {
    return (
        <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur-xl md:hidden">
            <div className="grid h-16 grid-cols-4">
                <a
                    href="#top"
                    className="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 transition hover:text-slate-950"
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    <Store className="h-4 w-4" />
                    <span>Inicio</span>
                </a>
                <a
                    href={primaryHref}
                    className="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 transition hover:text-slate-950"
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    <ShoppingBag className="h-4 w-4" />
                    <span>{primaryLabel}</span>
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
                ) : bookingUrl ? (
                    <Link
                        href={bookingUrl}
                        className="flex flex-col items-center justify-center gap-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 transition hover:text-slate-950"
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        <CalendarClock className="h-4 w-4" />
                        <span>Reservar</span>
                    </Link>
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
    isFollowing = false,
    followersCount = 0,
    seoStructuredData,
    isOpen = true,
    openingHours = [],
    canonicalUrl,
    previewMode = false,
    previewReason = null,
}) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [activeTab, setActiveTab] = useState('catalog');
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
    const safeServices = Array.isArray(tenant.services) ? tenant.services : [];
    const safeMenuSections = Array.isArray(tenant.menu_sections_overview) ? tenant.menu_sections_overview : [];
    const businessType = tenant.business_type || 'retail';
    const profile = tenant.storefront_profile || {
        label: 'Tienda y catalogo',
        hero_title: tenant.description || 'Micrositio publico del negocio.',
        hero_copy: tenant.slogan || 'Descubre este negocio dentro de Vito Business OS.',
        offer_title: 'Oferta del negocio',
        offer_copy: 'Explora lo que este negocio tiene listo para ti.',
        catalog_title: 'Catalogo',
        catalog_copy: 'Revisa lo disponible.',
        primary_cta_label: 'Ver catalogo',
        secondary_cta_label: 'Hablar por WhatsApp',
        mobile_tab_label: 'Catalogo',
        footer_copy: 'Micrositio conectado a Vito Business OS.',
    };
    const theme = BUSINESS_THEMES[businessType] ?? BUSINESS_THEMES.retail;
    const reviewCount = Number(tenant.reviews_count ?? (Array.isArray(tenant.reviews) ? tenant.reviews.length : 0));
    const cityName = typeof tenant.city === 'string' ? tenant.city : (tenant.city?.name || 'Honduras');
    const micrositeHref = route('tenant.microsite.show', { tenant: tenant.slug });
    const bookingUrl = route('public.booking.index', { tenant: tenant.slug });
    const ownerPanelHref = tenant?.slug ? route('filament.app.pages.dashboard', { tenant: tenant.slug }) : null;
    const whatsapp = tenant.whatsapp || tenant.whatsapp_number || '';
    const primaryColor = tenant.primary_color || tenant.brand_color || '#020617';
    const primaryWhatsAppUrl = isValidWhatsAppPhone(whatsapp)
        ? buildWhatsAppUrl(
            whatsapp,
            businessType === 'service'
                ? `Hola ${tenant.name}. Quiero mas informacion sobre sus servicios.`
                : businessType === 'food'
                    ? `Hola ${tenant.name}. Quiero hacer un pedido y conocer el menu disponible.`
                    : `Hola ${tenant.name}. Quiero mas informacion sobre su negocio.`
        )
        : null;
    const visibleTotal = Number(products?.total ?? safeProducts.length);
    const pageTitle = `${tenant.name || 'Negocio'} | ${profile.label} en ${cityName} | Vito Business OS`;
    const pageDescription = String(tenant.description || profile.hero_copy || `Descubre ${tenant.name} dentro de Vito Business OS.`).substring(0, 160);
    const pageImage = tenant.hero_image || tenant.cover || FALLBACK_IMAGE;

    const tabs = useMemo(() => {
        const items = [];

        if (businessType === 'hybrid') {
            items.push({ id: 'offers', label: profile.mobile_tab_label || 'Ofertas', anchor: '#ofertas' });
        } else if (businessType === 'service') {
            items.push({ id: 'services', label: profile.mobile_tab_label || 'Servicios', anchor: '#servicios' });
        } else {
            items.push({ id: 'catalog', label: profile.mobile_tab_label || 'Catalogo', anchor: '#catalogo' });
        }

        if (!items.some((item) => item.id === 'catalog') && (safeProducts.length > 0 || totalProductsCount > 0 || businessType !== 'service')) {
            items.push({ id: 'catalog', label: profile.catalog_title, anchor: '#catalogo' });
        }

        if (!items.some((item) => item.id === 'services') && safeServices.length > 0) {
            items.push({ id: 'services', label: 'Servicios', anchor: '#servicios' });
        }

        items.push({ id: 'info', label: 'Informacion', anchor: '#informacion' });
        items.push({ id: 'reviews', label: 'Resenas', anchor: '#resenas' });

        return items;
    }, [businessType, profile, safeProducts.length, totalProductsCount, safeServices.length]);

    useEffect(() => {
        if (!tabs.some((tab) => tab.id === activeTab)) {
            setActiveTab(tabs[0]?.id || 'catalog');
        }
    }, [activeTab, tabs]);

    const handleSearch = useCallback((event) => {
        event.preventDefault();
        router.get(
            micrositeHref,
            { search: searchQuery || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, [micrositeHref, searchQuery]);

    const activeMobileTab = tabs.find((tab) => tab.id === activeTab) ?? tabs[0];

    return (
        <div
            id="top"
            className="min-h-screen bg-white text-slate-950 selection:bg-slate-950 selection:text-white"
            data-testid="tenant-microsite"
            style={{
                fontFamily: PUBLIC_BODY_FONT,
                '--primary': primaryColor,
                '--primary-color': primaryColor,
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

            <TopNav isOwner={isOwner} ownerPanelHref={ownerPanelHref} businessLabel={theme.label} />

            <HeroSection
                tenant={tenant}
                profile={profile}
                businessType={businessType}
                tenantPlan={tenantPlan}
                cityName={cityName}
                isOpen={isOpen}
                totalProductsCount={visibleTotal}
                servicesCount={safeServices.length}
                menuSectionsCount={safeMenuSections.length}
                serviceProvidersCount={tenant.service_providers_count || 0}
                reviewCount={reviewCount}
                primaryWhatsAppUrl={primaryWhatsAppUrl}
                bookingUrl={bookingUrl}
                isFollowing={isFollowing}
                followersCount={followersCount}
                isOwner={isOwner}
                ownerPanelHref={ownerPanelHref}
                hasWebsite={Boolean(tenant.website_url)}
            />

            {previewMode ? (
                <PreviewBanner previewReason={previewReason} ownerPanelHref={ownerPanelHref} />
            ) : null}

            <FocusStrip
                businessType={businessType}
                services={safeServices}
                menuSections={safeMenuSections}
                products={safeProducts}
                bookingUrl={bookingUrl}
                primaryWhatsAppUrl={primaryWhatsAppUrl}
                profile={profile}
            />

            <VerticalShowcaseSection
                profile={profile}
                primaryWhatsAppUrl={primaryWhatsAppUrl}
                bookingUrl={businessType === 'service' || businessType === 'hybrid' ? bookingUrl : null}
            />

            <VerticalPlaybookSection
                profile={profile}
                businessType={businessType}
                productsCount={safeProducts.length}
                servicesCount={safeServices.length}
                menuSectionsCount={safeMenuSections.length}
                serviceProvidersCount={tenant.service_providers_count || 0}
                primaryWhatsAppUrl={primaryWhatsAppUrl}
                bookingUrl={businessType === 'service' || businessType === 'hybrid' ? bookingUrl : null}
                isOpen={isOpen}
            />

            <WorkspaceTabs tabs={tabs} activeTab={activeTab} onChange={setActiveTab} />

            <main id="workspace" className="mx-auto max-w-7xl space-y-10 px-6 py-10 pb-32">
                {activeTab === 'offers' ? (
                    <OffersPanel
                        businessType={businessType}
                        services={safeServices}
                        menuSections={safeMenuSections}
                        products={safeProducts}
                        bookingUrl={bookingUrl}
                        primaryWhatsAppUrl={primaryWhatsAppUrl}
                    />
                ) : null}

                {activeTab === 'catalog' ? (
                    <ProductPanel
                        businessType={businessType}
                        profile={profile}
                        panelTitle={profile.catalog_panel_title}
                        panelCopy={profile.catalog_panel_copy}
                        searchQuery={searchQuery}
                        setSearchQuery={setSearchQuery}
                        handleSearch={handleSearch}
                        products={safeProducts}
                        pagination={pagination}
                        tenant={tenant}
                        tenantPlan={tenantPlan}
                        hiddenProductsCount={hiddenProductsCount}
                        hasHiddenProducts={hasHiddenProducts}
                        visibleTotal={visibleTotal}
                        menuSections={safeMenuSections}
                    />
                ) : null}

                {activeTab === 'services' ? (
                    <ServicePanel
                        services={safeServices}
                        bookingUrl={bookingUrl}
                        serviceProvidersCount={tenant.service_providers_count || 0}
                        primaryWhatsAppUrl={primaryWhatsAppUrl}
                        profile={profile}
                    />
                ) : null}

                {activeTab === 'info' ? (
                    <InfoPanel tenant={tenant} isOpen={isOpen} openingHours={openingHours} />
                ) : null}

                {activeTab === 'reviews' ? (
                    <section id="resenas">
                        <ReviewsPanel tenant={tenant} />
                    </section>
                ) : null}
            </main>

            {tenantPlan !== PLAN.SEMILLA ? <FloatingCart /> : null}
            <MobileDock
                primaryLabel={activeMobileTab?.label || profile.mobile_tab_label || 'Inicio'}
                primaryHref={activeMobileTab?.anchor || '#workspace'}
                tenant={tenant}
                primaryWhatsAppUrl={primaryWhatsAppUrl}
                bookingUrl={businessType === 'service' || businessType === 'hybrid' ? bookingUrl : null}
            />
            <MicrositeFooter tenant={tenant} profile={profile} primaryWhatsAppUrl={primaryWhatsAppUrl} tenantPlan={tenantPlan} />
        </div>
    );
}

