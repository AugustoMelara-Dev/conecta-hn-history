/**
 * Tenant/Products/Index.jsx - Searchable Product Catalog
 *
 * FEATURES:
 * - Debounced Search (300ms, server-side via Inertia get)
 * - Pagination (Laravel links)
 * - Quota Indicator (product limits)
 * - Visibility Toggle (Optimistic UI)
 * - Delete with Confirmation
 *
 * PROPS (from ProductController@index):
 * @param {Object} props.products - Laravel Paginator { data: [], links: [], meta: {} }
 * @param {Object} props.filters - Current filters { search: string }
 * @param {Object} props.limits - Quota { current_count, limit, percentage, can_create }
 * @param {Object} props.tenant - { id, name, slug }
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { PackagePlus } from 'lucide-react';
import TenantLayout from '@/Layouts/TenantLayout';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyIcon,
    EmptyTitle,
} from '@/Components/ui/empty';

// ═══════════════════════════════════════════════════════════════════════════
// CONSTANTS
// ═══════════════════════════════════════════════════════════════════════════
const DEBOUNCE_MS = 300;

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 2,
});

function paginationLabel(label) {
    return String(label)
        .replace(/&laquo;/g, 'Anterior')
        .replace(/&raquo;/g, 'Siguiente')
        .replace(/<[^>]*>/g, '')
        .trim();
}

// ═══════════════════════════════════════════════════════════════════════════
// QUOTA BAR COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
function QuotaIndicator({ current, limit, percentage }) {
    const barColor = percentage >= 80 ? 'bg-red-500' : 'bg-blue-500';
    return (
        <div className="hidden md:flex items-center gap-3 text-sm">
            <span className="text-gray-500">{current} / {limit} productos</span>
            <div className="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                <div
                    className={`h-full ${barColor} rounded-full transition-all`}
                    style={{ width: `${Math.min(percentage, 100)}%` }}
                />
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// PRODUCT CARD COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
function ProductCard({ product, onToggleVisibility, onDelete }) {
    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md transition-shadow">
            {/* Image */}
            <div className="aspect-square bg-gray-100 relative overflow-hidden">
                {product.image_url ? (
                    <img
                        src={product.image_url}
                        alt={product.name}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                ) : (
                    <div className="w-full h-full flex items-center justify-center text-gray-400">
                        <svg className="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1}
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                )}

                {/* Visibility Badge */}
                <div className="absolute top-2 right-2">
                    <span className={`px-2 py-1 text-xs font-medium rounded-full ${product.is_visible
                        ? 'bg-emerald-500 text-white'
                        : 'bg-gray-500 text-white'
                        }`}>
                        {product.is_visible ? 'Visible' : 'Oculto'}
                    </span>
                </div>
            </div>

            {/* Content */}
            <div className="p-4">
                <h3 className="font-semibold text-gray-900 line-clamp-2 mb-1">{product.name}</h3>
                <p className="text-lg font-bold text-blue-600">
                    {currencyFormatter.format(product.price)}
                </p>

                {/* Actions */}
                <div className="mt-4 flex items-center gap-2">
                    {/* Visibility Toggle */}
                    <button
                        type="button"
                        onClick={() => onToggleVisibility(product.id)}
                        aria-pressed={product.is_visible}
                        aria-label={`${product.is_visible ? 'Ocultar' : 'Mostrar'} ${product.name}`}
                        className={`flex-1 py-2 px-3 text-sm font-medium rounded-lg transition-colors ${product.is_visible
                            ? 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100'
                            }`}
                    >
                        {product.is_visible ? 'Ocultar' : 'Mostrar'}
                    </button>

                    {/* Edit */}
                    <Link
                        href={route('tenant.products.edit', product.id)}
                        className="p-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors"
                        aria-label={`Editar ${product.name}`}
                        title={`Editar ${product.name}`}
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </Link>

                    {/* Delete */}
                    <button
                        type="button"
                        onClick={() => onDelete(product)}
                        className="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors"
                        aria-label={`Eliminar ${product.name}`}
                        title={`Eliminar ${product.name}`}
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// PAGINATION COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
function Pagination({ links }) {
    if (!links || links.length <= 3) return null;

    return (
        <nav className="flex items-center justify-center gap-1 mt-8" aria-label="Paginacion de productos">
            {links.map((link, index) => {
                const className = `px-3 py-2 text-sm rounded-lg transition-colors ${link.active
                    ? 'bg-blue-600 text-white'
                    : link.url
                        ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        : 'bg-gray-50 text-gray-400 cursor-not-allowed'
                    }`;

                if (!link.url) {
                    return (
                        <span
                            key={index}
                            className={className}
                            aria-disabled="true"
                        >
                            {paginationLabel(link.label)}
                        </span>
                    );
                }

                return (
                    <Link
                        key={index}
                        href={link.url}
                        preserveState
                        className={className}
                        aria-current={link.active ? 'page' : undefined}
                    >
                        {paginationLabel(link.label)}
                    </Link>
                );
            })}
        </nav>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// FIX #11: SKELETON LOADER
// Principle: Perceived Performance - visual feedback during async operations
// ═══════════════════════════════════════════════════════════════════════════
function ProductSkeleton() {
    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden animate-pulse">
            {/* Image skeleton */}
            <div className="aspect-square bg-gray-200" />
            {/* Content skeleton */}
            <div className="p-4 space-y-3">
                <div className="h-4 bg-gray-200 rounded w-3/4" />
                <div className="h-5 bg-gray-200 rounded w-1/2" />
                <div className="flex gap-2 mt-4">
                    <div className="h-9 bg-gray-200 rounded flex-1" />
                    <div className="h-9 w-9 bg-gray-200 rounded" />
                    <div className="h-9 w-9 bg-gray-200 rounded" />
                </div>
            </div>
        </div>
    );
}

function SkeletonGrid({ count = 8 }) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            {Array.from({ length: count }).map((_, i) => (
                <ProductSkeleton key={i} />
            ))}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function ProductsIndex({ products, filters, limits, tenant }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [optimisticProducts, setOptimisticProducts] = useState(products.data);
    const [deleteTarget, setDeleteTarget] = useState(null);

    // Keep optimistic products in sync with server data
    useEffect(() => {
        setOptimisticProducts(products.data);
    }, [products.data]);

    // ─────────────────────────────────────────────────────────────────────
    // FIX #7: Debounce with lifecycle control
    // Principle: Skip initial mount to prevent unnecessary HTTP request
    // ─────────────────────────────────────────────────────────────────────
    const isFirstRender = useRef(true);
    const [isSearching, setIsSearching] = useState(false);

    useEffect(() => {
        // Skip first render - no search on mount
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        setIsSearching(true);
        const timer = setTimeout(() => {
            router.get(
                route('tenant.products.index'),
                { search: search || undefined },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onFinish: () => setIsSearching(false),
                }
            );
        }, DEBOUNCE_MS);

        return () => clearTimeout(timer);
    }, [search]);

    // ─────────────────────────────────────────────────────────────────────
    // TOGGLE VISIBILITY (Optimistic UI)
    // ─────────────────────────────────────────────────────────────────────
    const handleToggleVisibility = useCallback((productId) => {
        // Optimistic update
        setOptimisticProducts(prev =>
            prev.map(p =>
                p.id === productId ? { ...p, is_visible: !p.is_visible } : p
            )
        );

        // Background sync
        router.put(
            route('tenant.products.toggle-visibility', productId),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    // Rollback on error
                    setOptimisticProducts(products.data);
                },
            }
        );
    }, [products.data]);

    // ─────────────────────────────────────────────────────────────────────
    // DELETE PRODUCT
    // ─────────────────────────────────────────────────────────────────────
    const requestDelete = useCallback((product) => {
        setDeleteTarget(product);
    }, []);

    const confirmDelete = useCallback(() => {
        if (!deleteTarget) {
            return;
        }

        router.delete(route('tenant.products.destroy', deleteTarget.id), {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    }, [deleteTarget]);

    return (
        <TenantLayout>
            <Head title="Mis Productos" />

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <header className="bg-white border-b border-gray-200 sticky top-0 z-40">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            <div className="flex items-center gap-4">
                                <Link href={`/app/${tenant?.slug}`} className="text-gray-500 hover:text-gray-700">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                    </svg>
                                </Link>
                                <h1 className="text-xl font-bold text-gray-900">Mis Productos</h1>
                            </div>

                            <QuotaIndicator
                                current={limits?.current_count || 0}
                                limit={limits?.limit || 10}
                                percentage={limits?.percentage || 0}
                            />
                        </div>
                    </div>
                </header>

                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Search and Actions Bar */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        {/* Search */}
                        <div className="relative flex-1 max-w-md">
                            <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <label htmlFor="product-search" className="sr-only">
                                Buscar productos
                            </label>
                            <input
                                id="product-search"
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar productos..."
                                className="w-full pl-11 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>

                        {/* Create Button */}
                        {limits?.can_create ? (
                            <Link
                                href={route('tenant.products.create')}
                                className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-all shadow-lg shadow-blue-500/25"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                </svg>
                                Nuevo Producto
                            </Link>
                        ) : (
                            <div className="relative group">
                                <button
                                    disabled
                                    className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gray-300 text-gray-500 font-semibold rounded-xl cursor-not-allowed"
                                >
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                    </svg>
                                    Nuevo Producto
                                </button>
                                <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">
                                    Límite del plan alcanzado
                                    <Link href={`/upgrade/${tenant?.slug}`} className="text-blue-300 hover:underline ml-1">
                                        Mejorar
                                    </Link>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Products Grid - with Skeleton Loading */}
                    {isSearching ? (
                        <SkeletonGrid count={8} />
                    ) : optimisticProducts.length > 0 ? (
                        <>
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                                {optimisticProducts.map((product) => (
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        onToggleVisibility={handleToggleVisibility}
                                        onDelete={requestDelete}
                                    />
                                ))}
                            </div>
                            <Pagination links={products.links} />
                        </>
                    ) : (
                        <Empty>
                            <EmptyHeader>
                                <EmptyIcon icon={PackagePlus} />
                                <EmptyTitle>
                                    {search ? 'No se encontraron productos' : 'Aun no tienes productos'}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {search
                                        ? 'Prueba con otro termino o limpia la busqueda para volver a ver tu catalogo.'
                                        : 'Agrega tu primer producto para que el negocio tenga una vitrina publica lista para vender.'}
                                </EmptyDescription>
                            </EmptyHeader>
                            {!search && limits?.can_create && (
                                <EmptyContent>
                                    <Button asChild>
                                        <Link href={route('tenant.products.create')}>
                                            <PackagePlus className="h-4 w-4" aria-hidden="true" />
                                            Crear mi primer producto
                                        </Link>
                                    </Button>
                                </EmptyContent>
                            )}
                        </Empty>
                    )}
                </main>
            </div>

            <AlertDialog open={Boolean(deleteTarget)} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Eliminar producto</AlertDialogTitle>
                        <AlertDialogDescription>
                            Vas a eliminar "{deleteTarget?.name}". Esta accion no se puede deshacer y el producto dejara de estar disponible en la vitrina.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction asChild>
                            <Button type="button" variant="destructive" onClick={confirmDelete}>
                                Eliminar producto
                            </Button>
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </TenantLayout>
    );
}
