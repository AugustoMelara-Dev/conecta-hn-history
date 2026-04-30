import FollowButton from '@/Components/Public/FollowButton';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import {
    ArrowUpRight,
    BadgeCheck,
    CalendarClock,
    Globe,
    MapPin,
    MessageCircle,
    Star,
    Users,
} from 'lucide-react';

import { BUSINESS_THEMES, FALLBACK_IMAGE, PLAN, PLAN_LABELS } from './micrositeConfig';
import { ActionLink, Eyebrow, MetricCard, SectionTitle } from './MicrositePrimitives';

function HeroLogo({ tenant }) {
    return (
        <div className="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-[1.75rem] border border-white/10 bg-white/10 shadow-2xl shadow-black/20 backdrop-blur-md">
            {tenant.logo ? (
                <img
                    src={tenant.logo}
                    alt={`${tenant.name} logo`}
                    className="h-full w-full object-cover"
                />
            ) : (
                <span className="font-headline text-3xl font-extrabold uppercase tracking-[0.12em] text-white">
                    {tenant.name?.slice(0, 2).toUpperCase()}
                </span>
            )}
        </div>
    );
}

function HeroBadgeRail({ theme, ThemeIcon, profile, tenant, tenantPlan }) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <Badge
                className={cn('gap-2 rounded-full px-3 py-1 font-label text-[11px] uppercase tracking-[0.18em]', theme.chipClass)}
                data-testid="storefront-vertical-label"
            >
                <ThemeIcon className="h-3.5 w-3.5" aria-hidden="true" />
                {profile.vertical_label || profile.label}
            </Badge>
            <Badge className="rounded-full bg-white/10 px-3 py-1 font-label text-[11px] uppercase tracking-[0.18em] text-white hover:bg-white/10">
                {tenant.category?.name || 'Negocio local'}
            </Badge>
            <Badge className="rounded-full bg-white/10 px-3 py-1 font-label text-[11px] uppercase tracking-[0.18em] text-white/80 hover:bg-white/10">
                {PLAN_LABELS[tenantPlan] || tenantPlan}
            </Badge>
            {tenantPlan !== PLAN.SEMILLA ? (
                <Badge className="gap-2 rounded-full bg-white px-3 py-1 font-label text-[11px] uppercase tracking-[0.18em] text-foreground hover:bg-white">
                    <BadgeCheck className="h-3.5 w-3.5" aria-hidden="true" />
                    Operacion lista
                </Badge>
            ) : null}
        </div>
    );
}

function HeroMetaList({ cityName, isOpen, tenant, reviewCount, followersCount }) {
    return (
        <div className="flex flex-wrap items-center gap-x-5 gap-y-3 font-label text-sm font-semibold text-white/80">
            <span className="inline-flex items-center gap-2">
                <MapPin className="h-4 w-4 text-white" aria-hidden="true" />
                {cityName}
            </span>
            <span className="inline-flex items-center gap-2">
                <span className={cn('h-2.5 w-2.5 rounded-full', isOpen ? 'bg-primary' : 'bg-destructive')} />
                {isOpen ? 'Abierto ahora' : 'Fuera de horario'}
            </span>
            {reviewCount > 0 ? (
                <span className="inline-flex items-center gap-2">
                    <Star className="h-4 w-4 fill-white text-white" aria-hidden="true" />
                    {tenant.rating || '4.8'} ({reviewCount})
                </span>
            ) : null}
            {followersCount > 0 ? (
                <span className="inline-flex items-center gap-2">
                    <Users className="h-4 w-4 text-white" aria-hidden="true" />
                    {followersCount} siguiendo este negocio
                </span>
            ) : null}
        </div>
    );
}

function HeroActionRow({
    tenant,
    isOwner,
    isFollowing,
    followersCount,
    primaryAction,
    secondaryAction,
    ownerPanelHref,
}) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            {!isOwner ? (
                <FollowButton
                    tenantId={tenant.id}
                    isFollowing={isFollowing}
                    followersCount={followersCount}
                    className="!rounded-full !border-white/15 !bg-white !px-5 !py-3 !text-xs !font-semibold !uppercase !tracking-[0.16em] !text-foreground hover:!bg-muted"
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
                <Button
                    asChild
                    variant="ghost"
                    className="h-auto rounded-full border border-white/20 bg-white/10 px-5 py-3 font-label text-xs font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:bg-white/16 hover:text-white"
                >
                    <a href={ownerPanelHref}>
                        Administrar
                        <ArrowUpRight data-icon="inline-end" aria-hidden="true" />
                    </a>
                </Button>
            ) : null}
        </div>
    );
}

function HeroInsightPanel({
    profile,
    businessType,
    servicesCount,
    totalProductsCount,
    reviewCount,
    menuSectionsCount,
    serviceProvidersCount,
}) {
    return (
        <aside className="rounded-[1.8rem] border border-white/10 bg-white/10 p-6 shadow-2xl shadow-slate-950/20 backdrop-blur-md">
            <Eyebrow dark>Modo de atención</Eyebrow>
            <SectionTitle dark className="mt-3 text-2xl">
                {profile.offer_title}
            </SectionTitle>
            <p className="mt-3 text-sm leading-7 text-white/80">
                {profile.offer_copy}
            </p>

            {Array.isArray(profile.trust_points) && profile.trust_points.length > 0 ? (
                <div className="mt-5 flex flex-wrap gap-2">
                    {profile.trust_points.slice(0, 3).map((point) => (
                        <Badge
                            key={point}
                            className="rounded-full border border-white/10 bg-white/10 px-3 py-1 font-label text-[11px] uppercase tracking-[0.14em] text-white hover:bg-white/10"
                        >
                            {point}
                        </Badge>
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
    );
}

export default function MicrositeHero({
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
                <div className="relative overflow-hidden rounded-[2.25rem] border border-border bg-foreground">
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
                        <div className="flex flex-col gap-6 sm:flex-row sm:items-start">
                            <HeroLogo tenant={tenant} />

                            <div className="min-w-0 flex-1">
                                <div className="flex flex-col gap-5">
                                    <HeroBadgeRail
                                        theme={theme}
                                        ThemeIcon={ThemeIcon}
                                        profile={profile}
                                        tenant={tenant}
                                        tenantPlan={tenantPlan}
                                    />

                                    <div>
                                        <h1 className="max-w-4xl font-headline text-4xl font-extrabold leading-[1.03] tracking-tight text-white md:text-6xl">
                                            {tenant.name}
                                        </h1>
                                        <p className="mt-4 max-w-3xl text-lg leading-8 text-white/80">
                                            {profile.hero_title}
                                        </p>
                                        <p className="mt-4 max-w-3xl text-base leading-8 text-white/70">
                                            {profile.hero_copy}
                                        </p>
                                    </div>

                                    <HeroMetaList
                                        cityName={cityName}
                                        isOpen={isOpen}
                                        tenant={tenant}
                                        reviewCount={reviewCount}
                                        followersCount={followersCount}
                                    />

                                    <HeroActionRow
                                        tenant={tenant}
                                        isOwner={isOwner}
                                        isFollowing={isFollowing}
                                        followersCount={followersCount}
                                        primaryAction={primaryAction}
                                        secondaryAction={secondaryAction}
                                        ownerPanelHref={ownerPanelHref}
                                    />
                                </div>
                            </div>
                        </div>

                        <HeroInsightPanel
                            profile={profile}
                            businessType={businessType}
                            servicesCount={servicesCount}
                            totalProductsCount={totalProductsCount}
                            reviewCount={reviewCount}
                            menuSectionsCount={menuSectionsCount}
                            serviceProvidersCount={serviceProvidersCount}
                        />
                    </div>
                </div>
            </div>
        </header>
    );
}
