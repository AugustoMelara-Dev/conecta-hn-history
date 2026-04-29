/**
 * StoreHome.jsx - The Catalog Discovery Page (ENTERPRISE v4)
 *
 * ARCHITECTURE:
 * - Scroll-Spy target with strict ID contract
 * - Browser-Native scroll offset via CSS scroll-margin
 * - Context-aware inventory checking
 * - SPA-compliant navigation (no window.location)
 * - Self-healing images with fallback
 * - Full OpenGraph meta for social sharing
 *
 * SECURITY:
 * - Validates stock against cart quantity before adding
 * - Prevents overselling via frontend guard
 *
 * OPTIMIZATIONS:
 * - Centralized Toast component
 * - Lazy loading for all images
 * - Memoized product cards for large catalogs
 *
 * ID CONTRACT:
 * Each section MUST have id={`category-${section.id}`}
 */

import { useState, useRef, useEffect, useMemo, memo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle, Plus, ShoppingBag } from 'lucide-react';
import StoreLayout from '@/Layouts/StoreLayout';
import useCartStore from '@/Stores/useCartStore';
import useTenantContext from '@/Hooks/useTenantContext';
import { formatCurrency } from '@/Utils/currency';
import ImageWithFallback from '@/Components/ImageWithFallback';
import Toast from '@/Components/Toast';
import { ActionFeedback, EmptyState, LoadingState } from '@/Components/Feedback/UXStates';

/**
 * ProductCard - Atomic product display with separated click areas
 * Memoized to prevent unnecessary re-renders in large catalogs
 */
const ProductCard = memo(function ProductCard({ product, tenant, onQuickAdd, isAddingDisabled, recentlyAdded }) {
    const productUrl = `/t/${tenant.slug}/product/${product.id}`;
    const isOutOfStock = product.manage_stock && product.stock_quantity <= 0;

    return (
        <div className="group bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
            {/* Image Area - Navigates to product details */}
            <Link href={productUrl} className="block relative aspect-square overflow-hidden">
                {product.image ? (
                    <ImageWithFallback
                        src={product.image}
                        alt={product.name}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        loading="lazy"
                        fadeIn
                    />
                ) : (
                    <div
                        className="w-full h-full flex items-center justify-center"
                        style={{ backgroundColor: `${tenant.primary_color}15` }}
                    >
                        <ShoppingBag
                            className="h-12 w-12 opacity-30"
                            style={{ color: tenant.primary_color }}
                            aria-hidden="true"
                        />
                    </div>
                )}

                {/* Out of Stock Badge */}
                {isOutOfStock && (
                    <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                        <span className="bg-white text-gray-900 px-3 py-1 rounded-full text-sm font-medium">
                            Agotado
                        </span>
                    </div>
                )}
            </Link>

            {/* Content Area */}
            <div className="p-4">
                {/* Title - Navigates to product details */}
                <Link href={productUrl}>
                    <h3 className="font-medium text-gray-900 line-clamp-2 hover:text-gray-600 transition-colors">
                        {product.name}
                    </h3>
                </Link>

                {/* Description */}
                {product.description && (
                    <p className="mt-1 text-sm text-gray-500 line-clamp-2">
                        {product.description}
                    </p>
                )}

                {/* Price Display with Discount Support */}
                <div className="mt-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        {/* Original Price (strikethrough if discounted) */}
                        {product.price_before_discount &&
                            product.price_before_discount > product.price && (
                                <span className="text-sm text-gray-400 line-through">
                                    {formatCurrency(product.price_before_discount)}
                                </span>
                            )}
                        {/* Current Price */}
                        <span
                            className="text-lg font-bold"
                            style={{ color: tenant.primary_color }}
                        >
                            {formatCurrency(product.price)}
                        </span>
                    </div>

                    {/* Quick Add Button - Separated click area */}
                    {!isOutOfStock && (
                        <button
                            type="button"
                            onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                onQuickAdd(product);
                            }}
                            disabled={isAddingDisabled}
                            className={`p-2 rounded-full text-white transition-all focus:outline-none focus:ring-2 focus:ring-offset-2
                                ${isAddingDisabled
                                    ? 'opacity-50 cursor-not-allowed'
                                    : 'hover:scale-110'
                                }`}
                            style={{
                                backgroundColor: tenant.primary_color,
                                '--tw-ring-color': tenant.primary_color,
                            }}
                            aria-label={`Agregar ${product.name} al carrito`}
                            data-testid={`store-add-to-cart-${product.id}`}
                        >
                            {recentlyAdded ? (
                                <CheckCircle className="h-5 w-5" aria-hidden="true" />
                            ) : (
                                <Plus className="h-5 w-5" aria-hidden="true" />
                            )}
                        </button>
                    )}
                </div>
                <ActionFeedback active={recentlyAdded} className="mt-3" testId={`store-add-feedback-${product.id}`}>
                    Agregado al carrito
                </ActionFeedback>
            </div>
        </div>
    );
});

function StoreBreadcrumbs({ tenant }) {
    const tenantHref = tenant.slug ? `/t/${tenant.slug}` : '/';

    return (
        <nav aria-label="Ubicación" className="mx-auto max-w-7xl px-4 pt-5 text-sm sm:px-6 lg:px-8">
            <ol className="flex flex-wrap items-center gap-2 text-slate-500">
                <li>
                    <Link href="/" className="font-medium transition hover:text-slate-900">
                        Inicio
                    </Link>
                </li>
                <li aria-hidden="true">/</li>
                <li>
                    <Link href={tenantHref} className="font-medium transition hover:text-slate-900">
                        {tenant.name || 'Negocio'}
                    </Link>
                </li>
                <li aria-hidden="true">/</li>
                <li className="font-semibold text-slate-900">Catálogo</li>
            </ol>
        </nav>
    );
}

/**
 * StoreHome - Main Catalog Page
 */
export default function StoreHome({ sections = [] }) {
    // Use centralized tenant context hook
    const { tenant, primaryColor } = useTenantContext();

    // Cart store for inventory validation
    const { items, addItem, openCart } = useCartStore();

    // Toast state with timeout cleanup and accumulator for rapid adds
    const [toast, setToast] = useState(null);
    const toastTimeoutRef = useRef(null);
    const toastCountRef = useRef(0); // Accumulator for rapid adds
    const [isHydrated, setIsHydrated] = useState(false);
    const [recentlyAddedProductId, setRecentlyAddedProductId] = useState(null);

    // ═══════════════════════════════════════════════════════════════════════
    // SEO: Prepare OpenGraph metadata for home page
    // SSR-safe: Uses tenant slug to build canonical URL
    // ═══════════════════════════════════════════════════════════════════════
    const seoData = useMemo(() => {
        const title = tenant.name || 'Tienda';
        const description = tenant.tagline || `Descubre los mejores productos en ${title}`;

        // Build base URL - SSR-safe with fallback
        const baseUrl = typeof window !== 'undefined'
            ? window.location.origin
            : (process.env.APP_URL || '');

        // Use banner or logo for og:image, with absolute URL
        const imageRaw = tenant.banner_url || tenant.logo || '/images/og-default.svg';
        const image = imageRaw.startsWith('http') ? imageRaw : `${baseUrl}${imageRaw}`;
        const pageUrl = `${baseUrl}/${tenant.slug || ''}`;

        return { title, description, image, pageUrl };
    }, [tenant.name, tenant.tagline, tenant.banner_url, tenant.logo, tenant.slug]);

    // ═══════════════════════════════════════════════════════════════════════
    // CLEANUP: Clear toast timeout on unmount to prevent memory leak
    // ═══════════════════════════════════════════════════════════════════════
    useEffect(() => {
        setIsHydrated(true);

        return () => {
            if (toastTimeoutRef.current) {
                clearTimeout(toastTimeoutRef.current);
            }
        };
    }, []);

    /**
     * Show toast notification with accumulator for rapid adds
     * If user clicks quickly, shows "Producto agregado x3" instead of resetting
     */
    const showToast = (message, type = 'success', isAddAction = false) => {
        // Clear any existing timeout
        if (toastTimeoutRef.current) {
            clearTimeout(toastTimeoutRef.current);
        }

        // Accumulate count for add actions
        if (isAddAction) {
            toastCountRef.current += 1;
            const count = toastCountRef.current;
            const displayMessage = count > 1
                ? `Producto agregado x${count}`
                : message;
            setToast({ message: displayMessage, type });
        } else {
            // Reset counter for non-add toasts
            toastCountRef.current = 0;
            setToast({ message, type });
        }

        // Set new timeout and store reference
        toastTimeoutRef.current = setTimeout(() => {
            setToast(null);
            toastTimeoutRef.current = null;
            toastCountRef.current = 0; // Reset counter when toast hides
        }, 3000);
    };

    /**
     * Hide toast manually
     */
    const hideToast = () => {
        if (toastTimeoutRef.current) {
            clearTimeout(toastTimeoutRef.current);
            toastTimeoutRef.current = null;
        }
        toastCountRef.current = 0;
        setToast(null);
    };

    /**
     * Context-Aware Quick Add with Inventory Validation
     */
    const handleQuickAdd = (product) => {
        // If product has modifiers, navigate to detail page (SPA-compliant)
        if (product.modifier_groups?.length > 0) {
            router.visit(`/t/${tenant.slug}/product/${product.id}`);
            return;
        }

        // ═══════════════════════════════════════════════════════════════════
        // CONTEXT-AWARE INVENTORY CHECK
        // Validates stock against current cart quantity before adding
        // ═══════════════════════════════════════════════════════════════════
        if (product.manage_stock) {
            // Find current quantity of this product in cart
            const cartItem = items.find((item) => item.productId === product.id);
            const currentCartQty = cartItem?.quantity || 0;
            const projectedQty = currentCartQty + 1;

            if (projectedQty > product.stock_quantity) {
                showToast(
                    `Solo quedan ${product.stock_quantity} unidades disponibles`,
                    'error'
                );
                return;
            }
        }

        // All validations passed - add to cart
        addItem(product, 1, [], '');
        setRecentlyAddedProductId(product.id);
        window.setTimeout(() => setRecentlyAddedProductId(null), 1200);
        openCart();
        showToast('Producto agregado al carrito', 'success', true); // isAddAction = true
    };

    return (
        <StoreLayout>
            {/* ═══════════════════════════════════════════════════════════════
                HEAD: SEO & OpenGraph Meta Tags for Catalog
            ═══════════════════════════════════════════════════════════════ */}
            <Head>
                <title>{seoData.title}</title>

                {/* Standard SEO Meta */}
                <meta name="description" content={seoData.description} />

                {/* OpenGraph for Facebook/WhatsApp */}
                <meta property="og:type" content="website" />
                <meta property="og:title" content={seoData.title} />
                <meta property="og:description" content={seoData.description} />
                <meta property="og:image" content={seoData.image} />
                <meta property="og:url" content={seoData.pageUrl} />
                <meta property="og:site_name" content={seoData.title} />

                {/* Twitter Card */}
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={seoData.title} />
                <meta name="twitter:description" content={seoData.description} />
                <meta name="twitter:image" content={seoData.image} />
            </Head>

            {/* Toast Notification - Centralized component */}
            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={hideToast}
                    primaryColor={primaryColor}
                />
            )}

            {/* ═══════════════════════════════════════════════════════════════
                HERO SECTION: Smart Banner with Gradient Fallback
            ═══════════════════════════════════════════════════════════════ */}
            <StoreBreadcrumbs tenant={tenant} />

            <section className="relative">
                {tenant.banner_url ? (
                    <div className="aspect-[21/9] w-full overflow-hidden">
                        <ImageWithFallback
                            src={tenant.banner_url}
                            alt={`Banner de ${tenant.name}`}
                            className="w-full h-full object-cover"
                            loading="eager"
                            fadeIn
                        />
                    </div>
                ) : (
                    /* Gradient Fallback using tenant's primary color */
                    <div
                        className="aspect-[21/9] w-full flex items-center justify-center"
                        style={{
                            background: `linear-gradient(135deg, ${primaryColor} 0%, ${primaryColor}80 50%, ${primaryColor}40 100%)`,
                        }}
                    >
                        <div className="text-center text-white">
                            <h1 className="text-3xl md:text-5xl font-bold drop-shadow-lg">
                                {tenant.name}
                            </h1>
                            {tenant.tagline && (
                                <p className="mt-2 text-lg md:text-xl opacity-90">
                                    {tenant.tagline}
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </section>

            {/* ═══════════════════════════════════════════════════════════════
                CATALOG SECTIONS: With scroll-margin for Browser-Native offset
            ═══════════════════════════════════════════════════════════════ */}
            <div className="max-w-7xl mx-auto px-4 py-8">
                {!isHydrated ? (
                    <LoadingState
                        title="Preparando catálogo"
                        description="Estamos cargando productos, imágenes y categorías del negocio."
                        testId="store-home-loading"
                    />
                ) : sections.length === 0 ? (
                    <EmptyState
                        icon={ShoppingBag}
                        eyebrow="Catálogo"
                        title="Este negocio todavía no tiene productos publicados"
                        description="Puedes volver más tarde o contactar al negocio si necesitas consultar disponibilidad."
                        actionLabel="Volver al inicio del negocio"
                        actionHref={tenant.slug ? `/t/${tenant.slug}` : '/'}
                        testId="store-empty-state"
                    />
                ) : (
                    sections.map((section) => (
                        <section
                            key={section.id}
                            /* ═══════════════════════════════════════════════════
                               ID CONTRACT: CategoryNav searches for this exact ID
                               scroll-mt-40: Browser-Native offset (160px)
                            ═══════════════════════════════════════════════════ */
                            id={`category-${section.id}`}
                            className="scroll-mt-40 mb-12"
                        >
                            {/* Section Header */}
                            <h2 className="text-2xl font-bold text-gray-900 mb-6">
                                {section.name}
                            </h2>

                            {/* Product Grid */}
                            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                                {section.products?.map((product) => (
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        tenant={tenant}
                                        onQuickAdd={handleQuickAdd}
                                        isAddingDisabled={recentlyAddedProductId === product.id}
                                        recentlyAdded={recentlyAddedProductId === product.id}
                                    />
                                ))}
                            </div>

                            {/* Empty Section State */}
                            {(!section.products || section.products.length === 0) && (
                                <EmptyState
                                    icon={ShoppingBag}
                                    eyebrow={section.name}
                                    title="No hay productos en esta categoría"
                                    description="Revisa otra categoría o vuelve más tarde cuando el negocio publique más opciones."
                                    actionLabel="Ver catálogo completo"
                                    actionHref={tenant.slug ? `/t/${tenant.slug}` : '/'}
                                    className="py-10"
                                    testId={`store-section-empty-${section.id}`}
                                />
                            )}
                        </section>
                    ))
                )}
            </div>
        </StoreLayout>
    );
}
