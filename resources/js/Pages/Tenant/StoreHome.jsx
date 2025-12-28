/**
 * StoreHome.jsx - The Catalog Discovery Page
 *
 * ARCHITECTURE:
 * - Scroll-Spy target with strict ID contract
 * - Browser-Native scroll offset via CSS scroll-margin
 * - Smart Hero with banner fallback gradient
 * - Responsive product grid
 *
 * ID CONTRACT:
 * Each section MUST have id={`category-${section.id}`}
 * This is the key that CategoryNav searches for.
 */

import { Head, Link, usePage } from '@inertiajs/react';
import { ShoppingBag, Plus } from 'lucide-react';
import StoreLayout from '@/Layouts/StoreLayout';
import useCartStore from '@/Stores/useCartStore';
import { useEffect } from 'react';

/**
 * Format currency to Honduran Lempiras
 */
const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-HN', {
        style: 'currency',
        currency: 'HNL',
        minimumFractionDigits: 0,
    }).format(amount || 0);
};

/**
 * ProductCard - Atomic product display with separated click areas
 */
function ProductCard({ product, tenant, onQuickAdd }) {
    const productUrl = `/${tenant.slug}/product/${product.id}`;

    return (
        <div className="group bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
            {/* Image Area - Navigates to product details */}
            <Link href={productUrl} className="block relative aspect-square overflow-hidden">
                {product.image ? (
                    <img
                        src={product.image}
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
                {product.manage_stock && product.stock_quantity <= 0 && (
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

                {/* Price & Add Button */}
                <div className="mt-3 flex items-center justify-between">
                    <span
                        className="text-lg font-bold"
                        style={{ color: tenant.primary_color }}
                    >
                        {formatCurrency(product.price)}
                    </span>

                    {/* Quick Add Button - Separated click area */}
                    {(!product.manage_stock || product.stock_quantity > 0) && (
                        <button
                            type="button"
                            onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                onQuickAdd(product);
                            }}
                            className="p-2 rounded-full text-white transition-all hover:scale-110 focus:outline-none focus:ring-2 focus:ring-offset-2"
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
 * StoreHome - Main Catalog Page
 */
export default function StoreHome({ tenant, sections = [] }) {
    const { props } = usePage();
    const tenantData = tenant || props.tenant || {};
    const primaryColor = tenantData.primary_color || '#000000';

    // Initialize cart for this tenant
    const { initForTenant, addItem, openCart } = useCartStore();

    useEffect(() => {
        if (tenantData.slug) {
            initForTenant(
                tenantData.slug,
                tenantData.whatsapp_number,
                tenantData.name,
                tenantData.id
            );
        }
    }, [tenantData.slug, tenantData.whatsapp_number, tenantData.name, tenantData.id, initForTenant]);

    /**
     * Quick add product to cart (no modifiers)
     */
    const handleQuickAdd = (product) => {
        // If product has modifiers, navigate to detail page instead
        if (product.modifier_groups?.length > 0) {
            window.location.href = `/${tenantData.slug}/product/${product.id}`;
            return;
        }

        addItem(product, 1, [], '');
        openCart();
    };

    return (
        <StoreLayout>
            <Head title={tenantData.name || 'Tienda'} />

            {/* ═══════════════════════════════════════════════════════════════
                HERO SECTION: Smart Banner with Gradient Fallback
            ═══════════════════════════════════════════════════════════════ */}
            <section className="relative">
                {tenantData.banner_url ? (
                    <div className="aspect-[21/9] w-full overflow-hidden">
                        <img
                            src={tenantData.banner_url}
                            alt={`Banner de ${tenantData.name}`}
                            className="w-full h-full object-cover"
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
                                {tenantData.name}
                            </h1>
                            {tenantData.tagline && (
                                <p className="mt-2 text-lg md:text-xl opacity-90">
                                    {tenantData.tagline}
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
                               scroll-mt-40: Browser-Native offset (160px = navbar + category nav)
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
                                        tenant={tenantData}
                                        onQuickAdd={handleQuickAdd}
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
