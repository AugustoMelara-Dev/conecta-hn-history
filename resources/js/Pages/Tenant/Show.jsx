/**
 * Tenant/Show.jsx - Unified Microsite View
 * 
 * ARCHITECTURE: Single Source of Truth (DRY Principle)
 * This is the ONE and ONLY view for tenant microsites.
 * It adapts dynamically based on tenantPlan using:
 * 1. Shared components (TenantHeader, ProductGrid, etc.)
 * 2. Strategy pattern (ProductActionArea)
 * 3. Conditional rendering for tier-specific features
 * 
 * NO separate Free/Pro pages exist - everything is unified here.
 */

import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Badge } from '@/Components/ui/badge';
import {
    MapPin, Clock, Globe, Star, MessageCircle, ShoppingBag,
    BadgeCheck, ExternalLink, Phone, Store, Lock
} from 'lucide-react';
import { cn } from '@/lib/utils';

// Tenant Components (Strategy Pattern + Shared)
import FloatingCart from '@/Components/Tenant/FloatingCart';
import ProductActionArea from '@/Components/Tenant/ProductActionArea';
import UpsellCard from '@/Components/Tenant/UpsellCard';
import useCartStore from '@/Stores/useCartStore';

// Color Utilities for WCAG-compliant contrast
import { getContrastColor } from '@/Utils/colorUtils';

/**
 * PLAN CONSTANTS
 */
const PLAN = {
    SEMILLA: 'semilla',
    PRO: 'pro',
    ENTERPRISE: 'enterprise',
};

export default function TenantShow({
    tenant,
    products,
    tenantPlan = PLAN.SEMILLA,
    // Soft Gate: Data awareness props from backend
    hasHiddenProducts = false,
    totalProductsCount = 0,
    hiddenProductsCount = 0,
    // Role awareness: Is viewer the tenant owner?
    isOwner = false,
    // Other props
    isFollowing,
    followersCount,
    seoStructuredData,
    isOpen = true,
    openingHours = [],
}) {
    // Cart initialization (Pro tier only)
    const initForTenant = useCartStore(state => state.initForTenant);

    useEffect(() => {
        if (tenantPlan !== PLAN.SEMILLA && tenant?.slug) {
            initForTenant(
                tenant.slug,
                tenant.whatsapp || tenant.whatsapp_number,
                tenant.name
            );
        }
    }, [tenant?.slug, tenantPlan]);

    // Loading state
    if (!tenant) {
        return (
            <div className="flex items-center justify-center min-h-screen bg-slate-950 text-white">
                <div className="text-center animate-pulse">
                    <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-slate-800 flex items-center justify-center">
                        <Store className="w-8 h-8 text-slate-600" />
                    </div>
                    <p className="text-lg font-medium text-slate-400">Cargando tienda...</p>
                </div>
            </div>
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DATA ABSTRACTION LAYER: Safe Fallbacks for all nullable fields
    // These constants guarantee non-null values for DOM injection
    // ═══════════════════════════════════════════════════════════════════════
    const safeProducts = Array.isArray(products) ? products : (Array.isArray(tenant.products) ? tenant.products : []);
    const primaryColor = tenant.brand_color || tenant.primary_color || '#6366f1';
    const primaryTextColor = getContrastColor(primaryColor);
    const isPro = tenantPlan !== PLAN.SEMILLA;
    const isRestaurant = ['Restaurantes', 'Comida', 'Restaurante'].includes(tenant.category?.name);
    const cityName = typeof tenant.city === 'string' ? tenant.city : (tenant.city?.name || 'Honduras');
    const whatsappNumber = tenant.whatsapp || tenant.whatsapp_number || '';

    // ═══════════════════════════════════════════════════════════════════════
    // SEO METADATA: Guaranteed safe strings (never null/undefined)
    // ═══════════════════════════════════════════════════════════════════════
    const pageTitle = String(tenant.name || 'Tienda Conecta HN');
    const pageDescription = String(
        tenant.description ||
        tenant.slogan ||
        `Descubre ${pageTitle} en Conecta HN. Tu marketplace local de Honduras.`
    );
    const pageImage = tenant.hero_image || tenant.cover || tenant.banner_path || null;

    return (
        <div
            style={{
                '--primary': primaryColor,
                '--primary-color': primaryColor,
                '--primary-text-color': primaryTextColor,
            }}
            className="min-h-screen bg-slate-950 font-sans"
        >
            {/* ═══════════════════════════════════════════════════════════════
                SEO HEAD: Defensive rendering with guaranteed string values
                - All content attributes are pre-sanitized
                - og:image only renders if image exists
            ═══════════════════════════════════════════════════════════════ */}
            <Head title={`${pageTitle} - Conecta HN`}>
                <meta name="description" content={pageDescription} />
                <meta property="og:title" content={`${pageTitle} - Conecta HN`} />
                <meta property="og:description" content={pageDescription} />
                <meta property="og:type" content="business.business" />
                {pageImage && <meta property="og:image" content={pageImage} />}
            </Head>

            {/* Fixed Navigation */}
            <nav className="fixed top-0 left-0 right-0 z-50 pointer-events-none">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <Link
                        href={route('directory.index')}
                        className="pointer-events-auto flex items-center gap-2 text-white/80 hover:text-white bg-black/30 hover:bg-black/50 backdrop-blur-md px-3 py-1.5 rounded-full transition-all text-sm font-medium mt-4"
                    >
                        ← Directorio
                    </Link>

                    {/* Branding for Semilla tier */}
                    {tenantPlan === PLAN.SEMILLA && (
                        <Link href="/" className="pointer-events-auto mt-4">
                            <div className="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center shadow-lg border border-white/10">
                                <span className="text-white font-bold text-sm">C</span>
                            </div>
                        </Link>
                    )}
                </div>
            </nav>

            {/* ═══════════════════════════════════════════════════════════════
                HERO SECTION - Adaptive based on plan
            ═══════════════════════════════════════════════════════════════ */}
            <header className="relative">
                {/* Cover Image */}
                <div className={cn(
                    "relative overflow-hidden bg-slate-900",
                    isPro ? "h-[40vh] md:h-[50vh]" : "h-48"
                )}>
                    {tenant.cover ? (
                        <img
                            src={tenant.cover}
                            alt={tenant.name}
                            className="w-full h-full object-cover opacity-70"
                        />
                    ) : (
                        <div
                            className="w-full h-full flex items-center justify-center"
                            style={{ backgroundColor: primaryColor }}
                        >
                            {isPro ? (
                                <span className="text-white/10 font-sans text-5xl md:text-9xl font-bold tracking-widest uppercase select-none">
                                    {tenant.name}
                                </span>
                            ) : (
                                <Store className="w-20 h-20 text-white/20" />
                            )}
                        </div>
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/30 to-transparent" />
                </div>

                {/* Profile Info */}
                <div className={cn(
                    "relative z-10 px-4 md:px-6 lg:px-8",
                    isPro
                        ? "absolute bottom-0 left-0 w-full p-6 md:p-12"
                        : "max-w-3xl mx-auto -mt-16 pb-6 text-center"
                )}>
                    <div className={cn(
                        "flex gap-6",
                        isPro
                            ? "flex-col md:flex-row items-end md:items-center max-w-7xl mx-auto"
                            : "flex-col items-center"
                    )}>
                        {/* Logo */}
                        <div className={cn(
                            "bg-slate-800 rounded-2xl border-4 border-slate-950 shadow-2xl overflow-hidden flex-shrink-0",
                            isPro ? "w-24 h-24 md:w-32 md:h-32" : "w-24 h-24"
                        )}>
                            {tenant.logo ? (
                                <img src={tenant.logo} alt="Logo" className="w-full h-full object-cover" />
                            ) : (
                                <div
                                    className="w-full h-full flex items-center justify-center"
                                    style={{ backgroundColor: primaryColor }}
                                >
                                    <span className="text-3xl font-bold text-white">
                                        {tenant.name?.substring(0, 2).toUpperCase()}
                                    </span>
                                </div>
                            )}
                        </div>

                        {/* Info */}
                        <div className={cn(isPro ? "flex-1 mb-2" : "")}>
                            <h1 className={cn(
                                "font-bold text-white flex items-center gap-3",
                                isPro ? "text-3xl md:text-5xl" : "text-2xl justify-center"
                            )}>
                                {tenant.name}
                                {isPro && <BadgeCheck className="w-8 h-8 text-indigo-400 fill-indigo-900/50" />}
                            </h1>

                            <div className={cn(
                                "flex flex-wrap items-center gap-3 text-white/90 mt-2",
                                !isPro && "justify-center"
                            )}>
                                <Badge className="bg-white/10 hover:bg-white/20 text-white border-none backdrop-blur-md">
                                    {tenant.category?.name || 'General'}
                                </Badge>

                                {/* Rating - Pro only */}
                                {isPro && (
                                    <>
                                        <div className="flex items-center gap-1">
                                            <Star className="w-4 h-4 fill-amber-400 text-amber-400" />
                                            <span className="font-bold">{tenant.rating || '4.8'}</span>
                                            <span className="opacity-70">({tenant.reviews_count || 0})</span>
                                        </div>
                                        <span className="hidden md:inline text-white/40">•</span>
                                    </>
                                )}

                                <div className="flex items-center gap-1 text-sm text-slate-300">
                                    <MapPin className="w-4 h-4" />
                                    <span>{cityName}</span>
                                </div>
                            </div>

                            {/* Status & Actions - Pro */}
                            {isPro && (
                                <div className="flex items-center gap-4 mt-4">
                                    <div className="flex items-center gap-2">
                                        <div className={cn(
                                            "w-2 h-2 rounded-full",
                                            isOpen ? "bg-emerald-500" : "bg-slate-500"
                                        )} />
                                        <span className={cn(
                                            "text-sm",
                                            isOpen ? "text-emerald-400" : "text-slate-400"
                                        )}>
                                            {isOpen ? 'Abierto' : 'Cerrado'}
                                        </span>
                                    </div>

                                    {whatsappNumber && (
                                        <a
                                            href={`https://wa.me/${whatsappNumber.replace(/\D/g, '')}?text=${encodeURIComponent(`Hola ${tenant.name}!`)}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-center gap-2 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-400 px-4 py-2 rounded-full text-sm font-medium transition-colors border border-emerald-500/20"
                                        >
                                            <MessageCircle className="w-4 h-4" />
                                            WhatsApp
                                        </a>
                                    )}
                                </div>
                            )}

                            {/* Contact Buttons - Semilla */}
                            {!isPro && (
                                <div className="flex gap-3 mt-4 justify-center">
                                    {whatsappNumber && (
                                        <a
                                            href={`https://wa.me/${whatsappNumber.replace(/\D/g, '')}?text=${encodeURIComponent(`Hola ${tenant.name}!`)}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors"
                                        >
                                            <MessageCircle className="w-4 h-4" />
                                            WhatsApp
                                        </a>
                                    )}
                                    {tenant.phone && (
                                        <a
                                            href={`tel:${tenant.phone}`}
                                            className="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors border border-white/10"
                                        >
                                            <Phone className="w-4 h-4" />
                                            Llamar
                                        </a>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </header>

            {/* ═══════════════════════════════════════════════════════════════
                MAIN CONTENT - Tabs for Pro, Simple list for Semilla
            ═══════════════════════════════════════════════════════════════ */}
            {isPro ? (
                // PRO TIER: Full tabs experience
                <Tabs defaultValue="products" className="w-full">
                    {/* Tab Navigation */}
                    <div className="sticky top-0 z-40 bg-slate-950/90 backdrop-blur-md border-b border-white/5">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <TabsList className="h-16 bg-transparent p-0 w-full justify-start gap-6">
                                <TabsTrigger
                                    value="products"
                                    className="h-full rounded-none border-b-2 border-transparent data-[state=active]:border-indigo-500 data-[state=active]:text-indigo-400 px-0 font-medium text-base bg-transparent text-slate-400"
                                >
                                    {isRestaurant ? 'Menú' : 'Catálogo'}
                                </TabsTrigger>
                                <TabsTrigger
                                    value="info"
                                    className="h-full rounded-none border-b-2 border-transparent data-[state=active]:border-indigo-500 data-[state=active]:text-indigo-400 px-0 font-medium text-base bg-transparent text-slate-400"
                                >
                                    Información
                                </TabsTrigger>
                                <TabsTrigger
                                    value="reviews"
                                    className="h-full rounded-none border-b-2 border-transparent data-[state=active]:border-indigo-500 data-[state=active]:text-indigo-400 px-0 font-medium text-base bg-transparent text-slate-400"
                                >
                                    Reseñas
                                </TabsTrigger>
                            </TabsList>
                        </div>
                    </div>

                    <main id="catalogo" className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-32">
                        {/* Products Tab */}
                        <TabsContent value="products" className="mt-0">
                            <ProductGridSection
                                products={safeProducts}
                                tenant={tenant}
                                tenantPlan={tenantPlan}
                                columns={3}
                                hasHiddenProducts={hasHiddenProducts}
                                hiddenProductsCount={hiddenProductsCount}
                                isOwner={isOwner}
                            />
                        </TabsContent>

                        {/* Info Tab */}
                        <TabsContent value="info" className="mt-8">
                            <InfoSection tenant={tenant} isOpen={isOpen} />
                        </TabsContent>

                        {/* Reviews Tab */}
                        <TabsContent value="reviews" className="mt-8">
                            <div className="text-center py-12 bg-slate-900/50 rounded-2xl border border-dashed border-white/10">
                                <MessageCircle className="w-12 h-12 text-slate-700 mx-auto mb-4" />
                                <h3 className="text-lg font-medium text-white">Reseñas</h3>
                                <p className="text-slate-400">Próximamente disponible.</p>
                            </div>
                        </TabsContent>
                    </main>
                </Tabs>
            ) : (
                // SEMILLA TIER: Simple product list
                <main className="max-w-3xl mx-auto px-4 py-8 pb-24">
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-lg font-bold text-white">Productos</h2>
                        <span className="text-sm text-slate-500">
                            {safeProducts.length} de {totalProductsCount}
                        </span>
                    </div>

                    <ProductGridSection
                        products={safeProducts}
                        tenant={tenant}
                        tenantPlan={tenantPlan}
                        columns={2}
                        hasHiddenProducts={hasHiddenProducts}
                        hiddenProductsCount={hiddenProductsCount}
                        isOwner={isOwner}
                    />

                    {/* Business Info */}
                    <div className="mt-8 bg-slate-900 rounded-2xl border border-white/5 p-6">
                        <h3 className="font-bold text-white mb-3">Información del negocio</h3>
                        <p className="text-sm text-slate-400 mb-4">
                            {tenant.description || 'Bienvenido a nuestro negocio.'}
                        </p>
                        <div className="space-y-3 text-sm text-slate-400">
                            <div className="flex items-center gap-3">
                                <MapPin className="w-4 h-4 text-slate-500" />
                                <span>{tenant.address || 'Honduras'}</span>
                            </div>
                            <div className="flex items-center gap-3">
                                <Clock className="w-4 h-4 text-slate-500" />
                                <span>{isOpen ? 'Abierto ahora' : 'Cerrado'}</span>
                            </div>
                        </div>
                    </div>
                </main>
            )}

            {/* ═══════════════════════════════════════════════════════════════
                FLOATING ELEMENTS - Conditional by plan
            ═══════════════════════════════════════════════════════════════ */}

            {/* Floating Cart - PRO ONLY (Desktop) */}
            {tenantPlan !== PLAN.SEMILLA && <FloatingCart />}

            {/* ═══════════════════════════════════════════════════════════════
                MOBILE SHOP NAVIGATION - Walled Garden Pattern
                Commerce-focused navigation: NO directory links, NO FAB
                Only actions that drive conversion within the store
            ═══════════════════════════════════════════════════════════════ */}
            <nav className="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-slate-950/95 backdrop-blur-xl border-t border-white/10 pb-safe">
                <div className="flex items-center justify-around h-16">
                    {/* Home - Store main */}
                    <Link
                        href={`/${tenant.slug}`}
                        className="flex flex-col items-center gap-1 text-slate-400 hover:text-white transition-colors px-4 py-2"
                    >
                        <Store className="w-5 h-5" />
                        <span className="text-[10px] font-medium">Inicio</span>
                    </Link>

                    {/* Catalog - Scroll to products */}
                    <a
                        href="#catalogo"
                        className="flex flex-col items-center gap-1 text-slate-400 hover:text-white transition-colors px-4 py-2"
                    >
                        <ShoppingBag className="w-5 h-5" />
                        <span className="text-[10px] font-medium">Catálogo</span>
                    </a>

                    {/* Phone - Direct call */}
                    {whatsappNumber && (
                        <a
                            href={`tel:${whatsappNumber}`}
                            className="flex flex-col items-center gap-1 text-slate-400 hover:text-white transition-colors px-4 py-2"
                        >
                            <Phone className="w-5 h-5" />
                            <span className="text-[10px] font-medium">Llamar</span>
                        </a>
                    )}

                    {/* WhatsApp - Primary CTA */}
                    {whatsappNumber && (
                        <a
                            href={`https://wa.me/${whatsappNumber.replace(/\D/g, '')}?text=${encodeURIComponent(`Hola ${tenant.name}!`)}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex flex-col items-center gap-1 px-4 py-2"
                            style={{ color: '#25D366' }}
                        >
                            <MessageCircle className="w-5 h-5" />
                            <span className="text-[10px] font-medium">WhatsApp</span>
                        </a>
                    )}
                </div>
            </nav>

            {/* Footer - Powered by Conecta HN (Desktop only) */}
            <footer className="hidden md:block py-6 text-center border-t border-white/5 mt-12">
                <p className="text-xs text-slate-500">
                    Powered by{' '}
                    <Link href="/" className="text-indigo-400 hover:text-indigo-300 transition-colors font-medium">
                        Conecta HN
                    </Link>
                </p>
            </footer>
        </div>
    );
}

/* ═══════════════════════════════════════════════════════════════════════════
   EXTRACTED COMPONENTS - Keep file self-contained but modular
══════════════════════════════════════════════════════════════════════════════ */

/**
 * ProductGridSection - Renders product grid with action buttons
 * Now includes Soft Gate UX: UpsellCard injection when products are hidden
 * Role-aware: Different UpsellCard content for owner vs customer
 */
function ProductGridSection({
    products,
    tenant,
    tenantPlan,
    columns = 3,
    hasHiddenProducts = false,
    hiddenProductsCount = 0,
    isOwner = false
}) {
    const isPro = tenantPlan !== PLAN.SEMILLA;

    // Empty state with Soft Gate awareness
    if (!products || products.length === 0) {
        // If there ARE hidden products but none visible, show special message
        if (hasHiddenProducts && hiddenProductsCount > 0) {
            return (
                <div className="text-center py-12 bg-slate-900/50 rounded-2xl border border-dashed border-indigo-500/20">
                    <Lock className="w-12 h-12 text-indigo-500 mx-auto mb-4" />
                    <h3 className="text-lg font-medium text-white">Productos disponibles en Plan Pro</h3>
                    <p className="text-slate-400 mb-4">
                        Este negocio tiene {hiddenProductsCount} productos que no se muestran en el Plan Semilla.
                    </p>
                    <Link
                        href="/"
                        className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                    >
                        Ver planes Pro
                    </Link>
                </div>
            );
        }

        return (
            <div className="text-center py-12 bg-slate-900/50 rounded-2xl border border-dashed border-white/10">
                <ShoppingBag className="w-12 h-12 text-slate-700 mx-auto mb-4" />
                <h3 className="text-lg font-medium text-white">Catálogo vacío</h3>
                <p className="text-slate-400">Este negocio aún no ha publicado productos.</p>
            </div>
        );
    }

    const hasModifiers = (product) => product.has_modifiers || (product.modifier_groups?.length > 0);

    return (
        <div className={cn(
            "grid gap-4",
            columns === 3
                ? "grid-cols-1 md:grid-cols-2 lg:grid-cols-3"
                : "grid-cols-1 sm:grid-cols-2"
        )}>
            {products.map((product) => (
                <div
                    key={product.id}
                    className="group bg-slate-900 rounded-2xl border border-white/5 overflow-hidden hover:border-indigo-500/30 transition-all"
                >
                    {/* Image */}
                    <div className="aspect-[4/3] bg-slate-800 relative overflow-hidden">
                        <img
                            src={product.image}
                            alt={product.name}
                            className="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all duration-500"
                            loading="lazy"
                        />

                        {/* Price Badge */}
                        <div className="absolute top-3 right-3 bg-black/60 backdrop-blur-md px-3 py-1.5 rounded-full text-sm font-bold text-white border border-white/10">
                            {hasModifiers(product) && isPro ? 'Desde ' : ''}
                            {product.formatted_price || `L. ${product.price}`}
                        </div>

                        {/* Modifiers badge - Pro only */}
                        {hasModifiers(product) && isPro && (
                            <div className="absolute bottom-3 left-3 bg-indigo-600/90 backdrop-blur-md px-2 py-1 rounded-full text-xs font-medium text-white">
                                {product.modifier_groups?.length || 0} opciones
                            </div>
                        )}
                    </div>

                    {/* Info */}
                    <div className="p-4">
                        <h3 className="font-bold text-white mb-1 truncate group-hover:text-indigo-400 transition-colors">
                            {product.name}
                        </h3>
                        <p className="text-sm text-slate-400 line-clamp-2 mb-4 min-h-[2.5rem]">
                            {product.description || 'Producto disponible.'}
                        </p>

                        {/* Strategy Pattern Action Button */}
                        <ProductActionArea
                            product={product}
                            tenantPlan={tenantPlan}
                            tenantWhatsapp={tenant.whatsapp || tenant.whatsapp_number}
                            tenantName={tenant.name}
                            tenantSlug={tenant.slug}
                            variant="default"
                            className="w-full"
                        />
                    </div>
                </div>
            ))}

            {/* ═══════════════════════════════════════════════════════════════
                SOFT GATE: UpsellCard injection when products are hidden
                Role-aware: Different content for owner vs customer
            ═══════════════════════════════════════════════════════════════ */}
            {hasHiddenProducts && (
                <UpsellCard
                    hiddenCount={hiddenProductsCount}
                    isOwner={isOwner}
                    tenantWhatsapp={tenant?.whatsapp || tenant?.whatsapp_number}
                    tenantName={tenant?.name}
                />
            )}
        </div>
    );
}

/**
 * InfoSection - Business information tab content
 */
function InfoSection({ tenant, isOpen }) {
    return (
        <div className="grid md:grid-cols-3 gap-8">
            <div className="md:col-span-2 space-y-6">
                <div className="bg-slate-900 border border-white/5 rounded-2xl p-6">
                    <h3 className="font-bold text-lg text-white mb-4">Sobre Nosotros</h3>
                    <p className="text-slate-400 leading-relaxed whitespace-pre-line">
                        {tenant.description || 'Bienvenido a nuestro negocio.'}
                    </p>
                </div>
            </div>
            <div className="space-y-6">
                <div className="bg-slate-900 border border-white/5 rounded-2xl p-6 space-y-4">
                    <div className="flex items-start gap-3">
                        <MapPin className="w-5 h-5 mt-0.5 text-indigo-400" />
                        <div>
                            <h4 className="font-medium text-white">Ubicación</h4>
                            <p className="text-sm text-slate-400">{tenant.address || 'No especificada'}</p>
                        </div>
                    </div>
                    <div className="flex items-start gap-3">
                        <Clock className="w-5 h-5 mt-0.5 text-indigo-400" />
                        <div>
                            <h4 className="font-medium text-white">Horario</h4>
                            <p className={cn("text-sm", isOpen ? "text-emerald-400" : "text-rose-400")}>
                                {isOpen ? 'Abierto ahora' : 'Cerrado'}
                            </p>
                        </div>
                    </div>
                    {tenant.website_url && (
                        <div className="flex items-start gap-3">
                            <Globe className="w-5 h-5 mt-0.5 text-indigo-400" />
                            <div>
                                <h4 className="font-medium text-white">Sitio Web</h4>
                                <a
                                    href={tenant.website_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-sm text-indigo-400 hover:underline flex items-center gap-1"
                                >
                                    Visitar <ExternalLink className="w-3 h-3" />
                                </a>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
