/**
 * StoreHome.jsx - The Catalog Discovery Page (ENTERPRISE v3)
 *
 * ARCHITECTURE:
 * - Scroll-Spy target with strict ID contract
 * - Browser-Native scroll offset via CSS scroll-margin
 * - Context-aware inventory checking
 * - SPA-compliant navigation (no window.location)
 * - Self-healing images with fallback
 *
 * SECURITY:
 * - Validates stock against cart quantity before adding
 * - Prevents overselling via frontend guard
 *
 * OPTIMIZATIONS:
 * - Toast timeout cleanup on unmount
 * - Lazy loading for all images
 * - ImageWithFallback for visual resilience
 *
 * ID CONTRACT:
 * Each section MUST have id={`category-${section.id}`}
 */

import { useState, useRef, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ShoppingBag, Plus, AlertCircle, X } from 'lucide-react';
import StoreLayout from '@/Layouts/StoreLayout';
import useCartStore from '@/Stores/useCartStore';
import useTenantContext from '@/Hooks/useTenantContext';
import { formatCurrency } from '@/Utils/currency';
import ImageWithFallback from '@/Components/ImageWithFallback';

/**
 * ProductCard - Atomic product display with separated click areas
 */
function ProductCard({ product, tenant, onQuickAdd, isAddingDisabled }) {
    const productUrl = `/${tenant.slug}/product/${product.id}`;
    const isOutOfStock = product.manage_stock && product.stock_quantity <= 0;

    return (
        <div className="group bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
            {/* Image Area - Navigates to product details */}
            <Link href={productUrl} className="block relative aspect-square overflow-hidden">
                {product.image ? (
                    <ImageWithFallback
                        src={product.image}
                        fallbackSrc="/images/placeholder.png"
                        alt={product.name}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        loading="lazy"
                    />
                ) : (
                    <div
                        className="w-full h-full flex items-center justify-center"
                        style={{ backgroundColor: `${tenant.primary_color}15` }}
                    >
                        <ShoppingBag
                            className="h-12 w-12 opacity-30"
                            style={{ color: tenant.primary_color }}
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
                        >
                            <Plus className="h-5 w-5" />
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

/**
 * Toast Notification Component
 */
function Toast({ message, type = 'success', onClose, primaryColor }) {
    const bgColor = type === 'error' ? '#ef4444' : primaryColor;
    const Icon = type === 'error' ? AlertCircle : ShoppingBag;

    return (
        <div
            role="alert"
            aria-live="polite"
            className="fixed top-20 left-1/2 -translate-x-1/2 z-50 animate-bounce"
        >
            <div
                className="flex items-center gap-2 px-4 py-3 rounded-lg shadow-lg text-white"
                style={{ backgroundColor: bgColor }}
            >
                <Icon className="h-5 w-5" />
                <span className="font-medium">{message}</span>
                <button
                    onClick={onClose}
                    className="ml-2 p-1 hover:bg-white/20 rounded"
                    aria-label="Cerrar notificación"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
        </div>
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

    // Toast state with timeout cleanup
    const [toast, setToast] = useState(null);
    const toastTimeoutRef = useRef(null);

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
     * Show toast notification with proper cleanup
     */
    const showToast = (message, type = 'success') => {
        // Clear any existing timeout
        if (toastTimeoutRef.current) {
            clearTimeout(toastTimeoutRef.current);
        }

        setToast({ message, type });

        // Set new timeout and store reference
        toastTimeoutRef.current = setTimeout(() => {
            setToast(null);
            toastTimeoutRef.current = null;
        }, 3000);
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
        showToast('Producto agregado al carrito');
    };

    return (
        <StoreLayout>
            <Head title={tenant.name || 'Tienda'} />

            {/* Toast Notification */}
            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => {
                        if (toastTimeoutRef.current) {
                            clearTimeout(toastTimeoutRef.current);
                            toastTimeoutRef.current = null;
                        }
                        setToast(null);
                    }}
                    primaryColor={primaryColor}
                />
            )}

            {/* ═══════════════════════════════════════════════════════════════
                HERO SECTION: Smart Banner with Gradient Fallback
            ═══════════════════════════════════════════════════════════════ */}
            <section className="relative">
                {tenant.banner_url ? (
                    <div className="aspect-[21/9] w-full overflow-hidden">
                        <ImageWithFallback
                            src={tenant.banner_url}
                            fallbackSrc="/images/banner-placeholder.png"
                            alt={`Banner de ${tenant.name}`}
                            className="w-full h-full object-cover"
                            loading="lazy"
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
                {sections.length === 0 ? (
                    <div className="text-center py-16">
                        <ShoppingBag className="h-16 w-16 mx-auto text-gray-300 mb-4" />
                        <p className="text-gray-500 text-lg">
                            No hay productos disponibles en este momento.
                        </p>
                    </div>
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
                                        isAddingDisabled={false}
                                    />
                                ))}
                            </div>

                            {/* Empty Section State */}
                            {(!section.products || section.products.length === 0) && (
                                <p className="text-gray-400 text-center py-8">
                                    No hay productos en esta categoría.
                                </p>
                            )}
                        </section>
                    ))
                )}
            </div>
        </StoreLayout>
    );
}
