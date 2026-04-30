import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import FloatingCart from '@/Components/Tenant/FloatingCart';
import MicrositeFooter from '@/Components/Tenant/Microsite/MicrositeFooter';
import MicrositeHero from '@/Components/Tenant/Microsite/MicrositeHero';
import MicrositeMobileDock from '@/Components/Tenant/Microsite/MicrositeMobileDock';
import MicrositeSeoHead from '@/Components/Tenant/Microsite/MicrositeSeoHead';
import MicrositeTopNav from '@/Components/Tenant/Microsite/MicrositeTopNav';
import MicrositeWorkspace from '@/Components/Tenant/Microsite/MicrositeWorkspace';
import {
    FocusStrip,
    VerticalPlaybookSection,
    VerticalShowcaseSection,
    WorkspaceTabs,
} from '@/Components/Tenant/Microsite/MicrositeGrowthSections';
import { BUSINESS_THEMES, FALLBACK_IMAGE, PLAN } from '@/Components/Tenant/Microsite/micrositeConfig';
import { Eyebrow } from '@/Components/Tenant/Microsite/MicrositePrimitives';
import { Button } from '@/Components/ui/button';
import useCartStore from '@/Stores/useCartStore';
import { buildWhatsAppUrl, isValidWhatsAppPhone } from '@/Utils/phoneUtils';
import { PUBLIC_BODY_FONT } from '@/lib/publicTypography';
import {
    ArrowUpRight,
    Store,
} from 'lucide-react';

function PreviewBanner({ previewReason, ownerPanelHref }) {
    return (
        <section className="px-6 pb-6">
            <div className="mx-auto flex max-w-7xl flex-col gap-4 rounded-[1.75rem] bg-foreground px-6 py-5 text-background md:flex-row md:items-center md:justify-between">
                <div>
                    <Eyebrow dark>Vista previa privada</Eyebrow>
                    <p className="mt-2 text-sm leading-7 text-background/75">
                        {previewReason || 'Este negocio todavía no está visible para visitantes.'}
                    </p>
                </div>
                {ownerPanelHref ? (
                    <Button
                        asChild
                        variant="secondary"
                        className="h-auto rounded-full px-5 py-3 font-label text-xs font-semibold uppercase tracking-[0.16em]"
                    >
                        <a href={ownerPanelHref}>
                            Abrir panel
                            <ArrowUpRight data-icon="inline-end" aria-hidden="true" />
                        </a>
                    </Button>
                ) : null}
            </div>
        </section>
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
            <div className="flex min-h-screen items-center justify-center bg-background text-foreground">
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
        label: 'Tienda y catálogo',
        hero_title: tenant.description || 'Micrositio público del negocio.',
        hero_copy: tenant.slogan || 'Descubre este negocio dentro de Vito Business OS.',
        offer_title: 'Oferta del negocio',
        offer_copy: 'Explora lo que este negocio tiene listo para ti.',
        catalog_title: 'Catálogo',
        catalog_copy: 'Revisa lo disponible.',
        primary_cta_label: 'Ver catálogo',
        secondary_cta_label: 'Hablar por WhatsApp',
        mobile_tab_label: 'Catálogo',
        footer_copy: 'Micrositio impulsado por Vito Business OS.',
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
                ? `Hola ${tenant.name}. Quiero más información sobre sus servicios.`
                : businessType === 'food'
                    ? `Hola ${tenant.name}. Quiero hacer un pedido y conocer el menú disponible.`
                    : `Hola ${tenant.name}. Quiero más información sobre su negocio.`
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
            items.push({ id: 'catalog', label: profile.mobile_tab_label || 'Catálogo', anchor: '#catalogo' });
        }

        if (!items.some((item) => item.id === 'catalog') && (safeProducts.length > 0 || totalProductsCount > 0 || businessType !== 'service')) {
            items.push({ id: 'catalog', label: profile.catalog_title, anchor: '#catalogo' });
        }

        if (!items.some((item) => item.id === 'services') && safeServices.length > 0) {
            items.push({ id: 'services', label: 'Servicios', anchor: '#servicios' });
        }

        items.push({ id: 'info', label: 'Información', anchor: '#informacion' });
        items.push({ id: 'reviews', label: 'Reseñas', anchor: '#resenas' });

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
            className="min-h-screen bg-background text-foreground selection:bg-primary selection:text-primary-foreground"
            data-testid="tenant-microsite"
            style={{
                fontFamily: PUBLIC_BODY_FONT,
                '--primary': primaryColor,
                '--primary-color': primaryColor,
                '--primary-text-color': '#ffffff',
            }}
        >
            <MicrositeSeoHead
                pageTitle={pageTitle}
                pageDescription={pageDescription}
                pageImage={pageImage}
                canonicalUrl={canonicalUrl}
                seoStructuredData={seoStructuredData}
            />

            <MicrositeTopNav isOwner={isOwner} ownerPanelHref={ownerPanelHref} businessLabel={theme.label} />

            <MicrositeHero
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

            <MicrositeWorkspace
                activeTab={activeTab}
                businessType={businessType}
                services={safeServices}
                menuSections={safeMenuSections}
                products={safeProducts}
                bookingUrl={bookingUrl}
                primaryWhatsAppUrl={primaryWhatsAppUrl}
                profile={profile}
                searchQuery={searchQuery}
                setSearchQuery={setSearchQuery}
                handleSearch={handleSearch}
                pagination={pagination}
                tenant={tenant}
                tenantPlan={tenantPlan}
                hiddenProductsCount={hiddenProductsCount}
                hasHiddenProducts={hasHiddenProducts}
                visibleTotal={visibleTotal}
                serviceProvidersCount={tenant.service_providers_count || 0}
                isOpen={isOpen}
                openingHours={openingHours}
            />

            {tenantPlan !== PLAN.SEMILLA ? <FloatingCart /> : null}
            <MicrositeMobileDock
                primaryLabel={activeMobileTab?.label || profile.mobile_tab_label || 'Inicio'}
                primaryHref={activeMobileTab?.anchor || '#workspace'}
                tenant={tenant}
                primaryWhatsAppUrl={primaryWhatsAppUrl}
                bookingUrl={businessType === 'service' || businessType === 'hybrid' ? bookingUrl : null}
            />
            <MicrositeFooter
                tenant={tenant}
                profile={profile}
                primaryWhatsAppUrl={primaryWhatsAppUrl}
                isSeedPlan={tenantPlan === PLAN.SEMILLA}
            />
        </div>
    );
}
