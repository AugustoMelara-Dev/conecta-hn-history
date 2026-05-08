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
import { ArrowRight, ImageOff, PackageSearch, Plus, ShoppingBag, Sparkles } from 'lucide-react';
import StoreLayout from '@/Layouts/StoreLayout';
import useCartStore from '@/Stores/useCartStore';
import useTenantContext from '@/Hooks/useTenantContext';
import { formatCurrency } from '@/Utils/currency';
import ImageWithFallback from '@/Components/ImageWithFallback';
import Toast from '@/Components/Toast';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';

/**
 * ProductCard - Atomic product display with separated click areas
 * Memoized to prevent unnecessary re-renders in large catalogs
 */
const ProductCard = memo(function ProductCard({ product, tenant, onQuickAdd, isAddingDisabled }) {
    const productUrl = `/${tenant.slug}/product/${product.id}`;
    const isOutOfStock = product.manage_stock && product.stock_quantity <= 0;
    const hasDiscount = product.price_before_discount &&
        product.price_before_discount > product.price;

    return (
        <Card
            data-testid="microsite-product-card"
            className="group flex h-full flex-col overflow-hidden transition-shadow hover:shadow-md"
        >
            <Link
                href={productUrl}
                className="relative block aspect-square overflow-hidden bg-muted"
                aria-label={`Ver ${product.name}`}
            >
                {product.image ? (
                    <ImageWithFallback
                        src={product.image}
                        alt={product.name}
                        className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                        loading="lazy"
                        fadeIn
                    />
                ) : (
                    <div className="flex size-full items-center justify-center text-muted-foreground">
                        <ImageOff className="size-12 opacity-60" aria-hidden="true" />
                    </div>
                )}

                {isOutOfStock && (
                    <div className="absolute inset-0 flex items-center justify-center bg-background/80 backdrop-blur-sm">
                        <Badge variant="secondary">Agotado</Badge>
                    </div>
                )}
            </Link>

            <CardHeader className="gap-2 p-4 pb-0">
                <div className="flex items-start justify-between gap-3">
                    <Link href={productUrl} className="min-w-0">
                        <CardTitle className="line-clamp-2 text-base leading-snug transition-colors hover:text-primary">
                            {product.name}
                        </CardTitle>
                    </Link>
                    {hasDiscount && <Badge variant="outline">Oferta</Badge>}
                </div>

                {product.description && (
                    <CardDescription className="line-clamp-2">
                        {product.description}
                    </CardDescription>
                )}
            </CardHeader>

            <CardContent className="flex flex-1 flex-col justify-end p-4 pt-4">
                <div className="flex flex-wrap items-baseline gap-2">
                    {hasDiscount && (
                        <span className="text-sm text-muted-foreground line-through">
                            {formatCurrency(product.price_before_discount)}
                        </span>
                    )}
                    <span className="text-lg font-semibold text-foreground">
                        {formatCurrency(product.price)}
                    </span>
                </div>
            </CardContent>

            <CardFooter className="p-4 pt-0">
                {!isOutOfStock ? (
                    <Button
                        type="button"
                        className="w-full"
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            onQuickAdd(product);
                        }}
                        disabled={isAddingDisabled}
                        aria-label={`Agregar ${product.name} al carrito`}
                        data-testid="microsite-add-to-cart"
                    >
                        <Plus data-icon="inline-start" aria-hidden="true" />
                        Agregar
                    </Button>
                ) : (
                    <Button type="button" variant="secondary" className="w-full" disabled>
                        Agotado
                    </Button>
                )}
            </CardFooter>
        </Card>
    );
});

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
            router.visit(`/${tenant.slug}/product/${product.id}`);
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
            <div data-testid="microsite-page" className="bg-background text-foreground">
                <section data-testid="microsite-hero" className="relative border-b bg-muted/30">
                    <div className="relative aspect-[16/10] overflow-hidden sm:aspect-[21/9]">
                        {tenant.banner_url ? (
                            <ImageWithFallback
                                src={tenant.banner_url}
                                alt={`Banner de ${tenant.name}`}
                                className="size-full object-cover"
                                loading="eager"
                                fadeIn
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center bg-muted text-muted-foreground">
                                <ShoppingBag className="size-16 opacity-60" aria-hidden="true" />
                            </div>
                        )}
                        <div className="absolute inset-0 bg-background/70" aria-hidden="true" />
                        <div className="absolute inset-x-0 bottom-0">
                            <div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="secondary">
                                        <Sparkles className="mr-1 size-3" aria-hidden="true" />
                                        Micrositio oficial
                                    </Badge>
                                    {sections.length > 0 && (
                                        <Badge variant="outline">
                                            {sections.length} {sections.length === 1 ? 'categoria' : 'categorias'}
                                        </Badge>
                                    )}
                                </div>
                                <div className="max-w-3xl">
                                    <h1 className="text-3xl font-semibold tracking-tight sm:text-5xl">
                                        {tenant.name || 'Tienda'}
                                    </h1>
                                    <p className="mt-3 max-w-2xl text-base text-muted-foreground sm:text-lg">
                                        {tenant.tagline || tenant.description || 'Explora productos, agrega al carrito y compra directamente desde el negocio.'}
                                    </p>
                                </div>
                                {sections[0] && (
                                    <div className="flex flex-wrap gap-3">
                                        <Button asChild>
                                            <a href={`#category-${sections[0].id}`}>
                                                Ver productos
                                                <ArrowRight data-icon="inline-end" aria-hidden="true" />
                                            </a>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                <div data-testid="microsite-tabs" className="border-b bg-background">
                    <div className="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-4 py-4 sm:px-6 lg:px-8">
                        {sections.length > 0 ? (
                            sections.map((section) => (
                                <Button key={section.id} asChild variant="outline" size="sm">
                                    <a href={`#category-${section.id}`}>{section.name}</a>
                                </Button>
                            ))
                        ) : (
                            <Badge variant="outline">Catalogo en preparacion</Badge>
                        )}
                    </div>
                </div>

            {/* ═══════════════════════════════════════════════════════════════
                CATALOG SECTIONS: With scroll-margin for Browser-Native offset
            ═══════════════════════════════════════════════════════════════ */}
            <div className="mx-auto flex max-w-7xl flex-col gap-10 px-4 py-8 sm:px-6 lg:px-8">
                {sections.length === 0 ? (
                    <Alert data-testid="microsite-product-empty" className="mx-auto max-w-2xl">
                        <PackageSearch className="mb-3 size-8 text-muted-foreground" aria-hidden="true" />
                        <AlertTitle>Catalogo en preparacion</AlertTitle>
                        <AlertDescription>
                            No hay productos disponibles en este momento. Vuelve pronto para ver las novedades de {tenant.name || 'este negocio'}.
                        </AlertDescription>
                    </Alert>
                ) : (
                    sections.map((section) => (
                        <section
                            key={section.id}
                            /* ═══════════════════════════════════════════════════
                               ID CONTRACT: CategoryNav searches for this exact ID
                               scroll-mt-40: Browser-Native offset (160px)
                            ═══════════════════════════════════════════════════ */
                            id={`category-${section.id}`}
                            className="scroll-mt-40"
                        >
                            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <Badge variant="secondary" className="mb-3">
                                        {section.products?.length || 0} productos
                                    </Badge>
                                    <h2 className="text-2xl font-semibold tracking-tight">
                                        {section.name}
                                    </h2>
                                </div>
                            </div>
                            <Separator className="mb-6" />

                            {section.products?.length > 0 ? (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                                    {section.products.map((product) => (
                                        <ProductCard
                                            key={product.id}
                                            product={product}
                                            tenant={tenant}
                                            onQuickAdd={handleQuickAdd}
                                            isAddingDisabled={false}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <Alert data-testid="microsite-product-empty">
                                    <AlertTitle>Sin productos en esta categoria</AlertTitle>
                                    <AlertDescription>
                                        Esta categoria todavia no tiene productos publicados.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </section>
                    ))
                )}
            </div>
            </div>
        </StoreLayout>
    );
}
