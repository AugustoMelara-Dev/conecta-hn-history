import { router } from '@inertiajs/react';
import { useRef, useCallback, useState, useEffect } from 'react';

/**
 * CrossSellGrid - Cross-Sell Component (v10 - A11y Enhanced)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  A11Y ENHANCEMENT: Dynamic aria-labels for loading state               ║
 * ║  SCREEN READER: Announces "Agregando {product} al carrito..."          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * ACCESSIBILITY:
 * - Loading state announced to screen readers via dynamic aria-label
 * - Error states have distinct aria-label
 * - Out-of-stock items clearly labeled as unavailable
 */
export default function CrossSellGrid({
    products = [],
    addToCartRoute = 'cart.add',
    title = '¿Te olvidaste de algo?',
}) {
    // ARRAYS for predictable React re-renders
    const [loadingProductIds, setLoadingProductIds] = useState([]);
    const [errorProductIds, setErrorProductIds] = useState([]);

    // REF for synchronous lock (not used in render)
    const processingIds = useRef(new Set());

    // CLEANUP: Track all timeout IDs for unmount cleanup
    const timeoutIds = useRef(new Set());

    // MEMORY HYGIENE: Clear all timeouts on unmount
    useEffect(() => {
        const currentTimeouts = timeoutIds.current;
        return () => {
            currentTimeouts.forEach((id) => clearTimeout(id));
            currentTimeouts.clear();
        };
    }, []);

    const handleAddToCart = useCallback((productId) => {
        // Lock check (synchronous via ref)
        if (processingIds.current.has(productId)) {
            return;
        }

        // Acquire lock
        processingIds.current.add(productId);

        // Update UI state
        setLoadingProductIds((prev) => [...prev, productId]);
        setErrorProductIds((prev) => prev.filter((id) => id !== productId));

        router.post(
            route(addToCartRoute),
            {
                product_id: productId,
                quantity: 1,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setErrorProductIds((prev) => prev.filter((id) => id !== productId));
                },
                onError: () => {
                    setErrorProductIds((prev) => [...prev, productId]);

                    // Auto-clear error with cleanup tracking
                    const timeoutId = setTimeout(() => {
                        setErrorProductIds((prev) => prev.filter((id) => id !== productId));
                        timeoutIds.current.delete(timeoutId);
                    }, 3000);

                    timeoutIds.current.add(timeoutId);
                },
                onFinish: () => {
                    // Release lock (ref first, then state)
                    processingIds.current.delete(productId);
                    setLoadingProductIds((prev) => prev.filter((id) => id !== productId));
                },
            }
        );
    }, [addToCartRoute]);

    const formatPrice = (price) => {
        return new Intl.NumberFormat('es-HN', {
            style: 'currency',
            currency: 'HNL',
            minimumFractionDigits: 2,
        }).format(price);
    };

    /**
     * Generate accessible aria-label based on product state.
     * A11Y: Provides feedback to screen readers during loading.
     */
    const getAriaLabel = (product, isLoading, hasError, isInStock) => {
        if (isLoading) {
            // A11Y: Announce loading state to screen readers
            return `Agregando ${product.name} al carrito...`;
        }

        if (hasError) {
            return `Error al agregar ${product.name}. Intenta de nuevo.`;
        }

        if (!isInStock) {
            return `${product.name} no disponible`;
        }

        return `Agregar ${product.name} al carrito`;
    };

    if (!products || products.length === 0) {
        return null;
    }

    return (
        <div className="bg-gray-50 rounded-xl p-4 mt-6">
            <h3 className="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <PlusIcon />
                {title}
            </h3>

            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                {products.map((product) => {
                    const isInStock = product.is_in_stock === true;
                    const isLoading = loadingProductIds.includes(product.id);
                    const hasError = errorProductIds.includes(product.id);
                    const isDisabled = isLoading || !isInStock;

                    return (
                        <div
                            key={product.id}
                            className={`
                                bg-white rounded-lg shadow-sm overflow-hidden flex flex-col
                                transition-opacity duration-200
                                ${!isInStock ? 'opacity-60' : ''}
                                ${hasError ? 'ring-2 ring-red-300' : ''}
                            `}
                        >
                            <ProductImage product={product} isInStock={isInStock} />

                            <div className="p-2 flex-1 flex flex-col">
                                <p className="text-xs font-medium text-gray-800 line-clamp-2 mb-1" title={product.name}>
                                    {product.name}
                                </p>

                                <p className="text-sm font-bold text-emerald-600 mb-2">
                                    {formatPrice(product.price)}
                                </p>

                                <button
                                    onClick={() => handleAddToCart(product.id)}
                                    disabled={isDisabled}
                                    // A11Y: Dynamic label announces loading state
                                    aria-label={getAriaLabel(product, isLoading, hasError, isInStock)}
                                    aria-busy={isLoading}
                                    className={`
                                        w-full py-1.5 px-2 rounded-md text-xs font-semibold
                                        transition-all duration-150
                                        ${hasError
                                            ? 'bg-red-100 text-red-600'
                                            : isDisabled
                                                ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                                                : 'bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95'
                                        }
                                    `}
                                >
                                    {isLoading ? (
                                        <span className="flex items-center justify-center gap-1">
                                            <SpinnerIcon />
                                            <span aria-hidden="true">Agregando...</span>
                                        </span>
                                    ) : hasError ? (
                                        'Error'
                                    ) : !isInStock ? (
                                        'No disponible'
                                    ) : (
                                        '+ Agregar'
                                    )}
                                </button>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// EXTRACTED COMPONENTS
// ═══════════════════════════════════════════════════════════════════════════

function ProductImage({ product, isInStock }) {
    return (
        <div className="aspect-square bg-gray-100 relative">
            {product.image_url ? (
                <img
                    src={product.image_url}
                    alt={`Imagen de ${product.name}`}
                    className="w-full h-full object-cover"
                    loading="lazy"
                />
            ) : (
                <div className="w-full h-full flex items-center justify-center">
                    <ImagePlaceholderIcon />
                </div>
            )}

            {!isInStock && (
                <div className="absolute inset-0 bg-gray-900/40 flex items-center justify-center">
                    <span className="bg-white/90 text-gray-700 text-xs font-semibold px-2 py-1 rounded">
                        Agotado
                    </span>
                </div>
            )}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// ICON COMPONENTS
// ═══════════════════════════════════════════════════════════════════════════

function PlusIcon() {
    return (
        <svg className="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
    );
}

function ImagePlaceholderIcon() {
    return (
        <svg className="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
    );
}

function SpinnerIcon() {
    return (
        <svg className="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
    );
}
