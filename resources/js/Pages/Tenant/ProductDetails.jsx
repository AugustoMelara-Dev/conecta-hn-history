/**
 * ProductDetails.jsx - Product Configuration Engine (ENTERPRISE v3)
 *
 * ARCHITECTURE:
 * - Modifier state management with validation
 * - RadioGroup for single-select, Checkboxes for multi-select
 * - Quantity clamping with stock limits (CONSIDERS CART QUANTITY)
 * - WCAG-compliant accessible notifications
 * - OpenGraph meta tags for social sharing
 * - Self-healing images with fallback
 *
 * SECURITY:
 * - Prevents quantity exceeding stock via input clamping
 * - Validates all required modifiers before cart injection
 * - Accounts for existing cart items when calculating max quantity
 *
 * SEO:
 * - Dynamic og:title, og:description, og:image
 * - Product price metadata for commerce indexing
 */

import { useState, useMemo, useRef, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { RadioGroup } from '@headlessui/react';
import {
    ArrowLeft,
    ShoppingCart,
    Check,
    Minus,
    Plus,
    AlertCircle,
    CheckCircle,
    X,
} from 'lucide-react';
import StoreLayout from '@/Layouts/StoreLayout';
import useCartStore from '@/Stores/useCartStore';
import useTenantContext from '@/Hooks/useTenantContext';
import { formatCurrency } from '@/Utils/currency';
import ImageWithFallback from '@/Components/ImageWithFallback';

/**
 * Truncate text for SEO meta descriptions (max 160 chars)
 */
function truncateDescription(text, maxLength = 160) {
    if (!text) return '';
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength - 3) + '...';
}

/**
 * ModifierGroupRadio - Single selection modifier group
 */
function ModifierGroupRadio({ group, selectedOption, onSelect, primaryColor }) {
    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="font-semibold text-gray-900">
                    {group.name}
                    {group.required && (
                        <span className="ml-2 text-xs text-red-500 font-medium">
                            * Requerido
                        </span>
                    )}
                </h3>
            </div>

            <RadioGroup value={selectedOption} onChange={onSelect}>
                <RadioGroup.Label className="sr-only">{group.name}</RadioGroup.Label>
                <div className="space-y-2">
                    {group.options?.map((option) => (
                        <RadioGroup.Option
                            key={option.id}
                            value={option.id}
                            className={({ active, checked }) =>
                                `relative flex cursor-pointer rounded-lg border p-4 transition-all focus:outline-none
                                ${checked
                                    ? 'border-2 bg-opacity-10'
                                    : 'border-gray-200 hover:border-gray-300'
                                }
                                ${active ? 'ring-2 ring-offset-2' : ''}`
                            }
                            style={{
                                borderColor: selectedOption === option.id ? primaryColor : undefined,
                                backgroundColor: selectedOption === option.id ? `${primaryColor}10` : undefined,
                                '--tw-ring-color': primaryColor,
                            }}
                        >
                            {({ checked }) => (
                                <div className="flex w-full items-center justify-between">
                                    <div className="flex items-center">
                                        <div
                                            className={`flex h-5 w-5 items-center justify-center rounded-full border-2 mr-3 transition-colors
                                                ${checked ? 'border-transparent' : 'border-gray-300'}`}
                                            style={{
                                                backgroundColor: checked ? primaryColor : 'transparent',
                                            }}
                                        >
                                            {checked && <Check className="h-3 w-3 text-white" />}
                                        </div>
                                        <span className="text-gray-900 font-medium">
                                            {option.name}
                                        </span>
                                    </div>
                                    {option.price > 0 && (
                                        <span className="text-sm text-gray-500">
                                            + {formatCurrency(option.price)}
                                        </span>
                                    )}
                                </div>
                            )}
                        </RadioGroup.Option>
                    ))}
                </div>
            </RadioGroup>
        </div>
    );
}

/**
 * ModifierGroupCheckbox - Multi-selection modifier group
 */
function ModifierGroupCheckbox({ group, selectedOptions = [], onToggle, primaryColor }) {
    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="font-semibold text-gray-900">
                    {group.name}
                    {group.required && (
                        <span className="ml-2 text-xs text-red-500 font-medium">
                            * Requerido
                        </span>
                    )}
                </h3>
                <span className="text-sm text-gray-500">
                    Máximo: {group.max_selections}
                </span>
            </div>

            <div className="space-y-2">
                {group.options?.map((option) => {
                    const isSelected = selectedOptions.includes(option.id);
                    const isDisabled =
                        !isSelected && selectedOptions.length >= group.max_selections;

                    return (
                        <button
                            key={option.id}
                            type="button"
                            onClick={() => onToggle(option.id)}
                            disabled={isDisabled}
                            className={`w-full flex items-center justify-between rounded-lg border p-4 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2
                                ${isSelected
                                    ? 'border-2 bg-opacity-10'
                                    : 'border-gray-200 hover:border-gray-300'
                                }
                                ${isDisabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
                            style={{
                                borderColor: isSelected ? primaryColor : undefined,
                                backgroundColor: isSelected ? `${primaryColor}10` : undefined,
                                '--tw-ring-color': primaryColor,
                            }}
                            aria-pressed={isSelected}
                            aria-label={`${option.name}${option.price > 0 ? `, + ${formatCurrency(option.price)}` : ''}`}
                        >
                            <div className="flex items-center">
                                <div
                                    className={`flex h-5 w-5 items-center justify-center rounded border-2 mr-3 transition-colors
                                        ${isSelected ? 'border-transparent' : 'border-gray-300'}`}
                                    style={{
                                        backgroundColor: isSelected ? primaryColor : 'transparent',
                                    }}
                                >
                                    {isSelected && <Check className="h-3 w-3 text-white" />}
                                </div>
                                <span className="text-gray-900 font-medium">
                                    {option.name}
                                </span>
                            </div>
                            {option.price > 0 && (
                                <span className="text-sm text-gray-500">
                                    + {formatCurrency(option.price)}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

/**
 * Accessible Toast Notification
 */
function Toast({ message, type = 'success', onClose, primaryColor }) {
    const bgColor = type === 'error' ? '#ef4444' : primaryColor;
    const Icon = type === 'error' ? AlertCircle : CheckCircle;

    return (
        <div
            role="alert"
            aria-live="polite"
            className="fixed top-20 left-1/2 -translate-x-1/2 z-50"
        >
            <div
                className="flex items-center gap-2 px-4 py-3 rounded-lg shadow-lg text-white animate-bounce"
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
 * ProductDetails - Main Product Configuration Page
 */
export default function ProductDetails({ product }) {
    // Use centralized tenant context hook
    const { tenant, primaryColor } = useTenantContext();

    // ═══════════════════════════════════════════════════════════════════════
    // STATE: Modifier selections & quantity
    // ═══════════════════════════════════════════════════════════════════════
    const [modifiers, setModifiers] = useState({});
    const [quantity, setQuantity] = useState(1);
    const [notes, setNotes] = useState('');
    const [toast, setToast] = useState(null);
    const toastTimeoutRef = useRef(null);

    // Cart store - need items to check existing quantity
    const { items, addItem, openCart } = useCartStore();

    // ═══════════════════════════════════════════════════════════════════════
    // SEO: Prepare OpenGraph metadata
    // ═══════════════════════════════════════════════════════════════════════
    const seoData = useMemo(() => {
        const title = product?.name || 'Producto';
        const description = truncateDescription(product?.description || `Descubre ${title} en nuestra tienda`);
        const image = product?.image || tenant?.logo || '/images/og-default.png';
        const price = product?.price || 0;

        return { title, description, image, price };
    }, [product?.name, product?.description, product?.image, product?.price, tenant?.logo]);

    // ═══════════════════════════════════════════════════════════════════════
    // CLEANUP: Clear toast timeout on unmount
    // ═══════════════════════════════════════════════════════════════════════
    useEffect(() => {
        return () => {
            if (toastTimeoutRef.current) {
                clearTimeout(toastTimeoutRef.current);
            }
        };
    }, []);

    // ═══════════════════════════════════════════════════════════════════════
    // Calculate existing quantity in cart for this product
    // ═══════════════════════════════════════════════════════════════════════
    const existingCartQty = useMemo(() => {
        if (!product?.id) return 0;
        const cartItem = items.find((item) => item.productId === product.id);
        return cartItem?.quantity || 0;
    }, [items, product?.id]);

    // ═══════════════════════════════════════════════════════════════════════
    // QUANTITY LIMITS: Derived from stock MINUS what's already in cart
    // ═══════════════════════════════════════════════════════════════════════
    const maxQuantity = useMemo(() => {
        if (product?.manage_stock) {
            const available = (product.stock_quantity || 0) - existingCartQty;
            return Math.max(1, available);
        }
        return 99;
    }, [product?.manage_stock, product?.stock_quantity, existingCartQty]);

    // ═══════════════════════════════════════════════════════════════════════
    // MODIFIER HANDLERS
    // ═══════════════════════════════════════════════════════════════════════
    const handleRadioSelect = (groupId, optionId) => {
        setModifiers((prev) => ({
            ...prev,
            [groupId]: optionId,
        }));
    };

    const handleCheckboxToggle = (groupId, optionId, maxSelections) => {
        setModifiers((prev) => {
            const current = prev[groupId] || [];
            const isSelected = current.includes(optionId);

            if (isSelected) {
                return {
                    ...prev,
                    [groupId]: current.filter((id) => id !== optionId),
                };
            } else if (current.length < maxSelections) {
                return {
                    ...prev,
                    [groupId]: [...current, optionId],
                };
            }
            return prev;
        });
    };

    // ═══════════════════════════════════════════════════════════════════════
    // QUANTITY HANDLERS: With input clamping
    // ═══════════════════════════════════════════════════════════════════════
    const handleIncrement = () => {
        setQuantity((q) => Math.min(maxQuantity, q + 1));
    };

    const handleDecrement = () => {
        setQuantity((q) => Math.max(1, q - 1));
    };

    // ═══════════════════════════════════════════════════════════════════════
    // GATEKEEPER: Validation for required modifiers
    // ═══════════════════════════════════════════════════════════════════════
    const isSelectionComplete = useMemo(() => {
        if (!product?.modifier_groups) return true;

        for (const group of product.modifier_groups) {
            if (group.required) {
                const selection = modifiers[group.id];

                if (group.max_selections === 1) {
                    if (!selection) return false;
                } else {
                    if (!selection || selection.length === 0) return false;
                }
            }
        }
        return true;
    }, [product?.modifier_groups, modifiers]);

    // ═══════════════════════════════════════════════════════════════════════
    // PRICE CALCULATION: Base + modifiers * quantity
    // ═══════════════════════════════════════════════════════════════════════
    const calculatedPrice = useMemo(() => {
        let total = product?.price || 0;

        if (product?.modifier_groups) {
            for (const group of product.modifier_groups) {
                const selection = modifiers[group.id];

                if (selection) {
                    if (Array.isArray(selection)) {
                        for (const optionId of selection) {
                            const option = group.options?.find((o) => o.id === optionId);
                            if (option) total += option.price || 0;
                        }
                    } else {
                        const option = group.options?.find((o) => o.id === selection);
                        if (option) total += option.price || 0;
                    }
                }
            }
        }

        return total * quantity;
    }, [product, modifiers, quantity]);

    // ═══════════════════════════════════════════════════════════════════════
    // TOAST HELPER with proper cleanup
    // ═══════════════════════════════════════════════════════════════════════
    const showToast = (message, type = 'success') => {
        if (toastTimeoutRef.current) {
            clearTimeout(toastTimeoutRef.current);
        }

        setToast({ message, type });

        toastTimeoutRef.current = setTimeout(() => {
            setToast(null);
            toastTimeoutRef.current = null;
        }, 3000);
    };

    // ═══════════════════════════════════════════════════════════════════════
    // CART INJECTION
    // ═══════════════════════════════════════════════════════════════════════
    const handleAddToCart = () => {
        if (!isSelectionComplete) return;

        // Additional stock validation before adding
        if (product?.manage_stock) {
            const projectedTotal = existingCartQty + quantity;
            if (projectedTotal > product.stock_quantity) {
                showToast(
                    `Solo puedes agregar ${maxQuantity} unidades más`,
                    'error'
                );
                return;
            }
        }

        // Build selected modifiers array for cart store
        const selectedModifiers = [];

        if (product?.modifier_groups) {
            for (const group of product.modifier_groups) {
                const selection = modifiers[group.id];

                if (selection) {
                    if (Array.isArray(selection)) {
                        for (const optionId of selection) {
                            const option = group.options?.find((o) => o.id === optionId);
                            if (option) {
                                selectedModifiers.push({
                                    groupId: group.id,
                                    groupName: group.name,
                                    optionId: option.id,
                                    name: option.name,
                                    price: option.price || 0,
                                });
                            }
                        }
                    } else {
                        const option = group.options?.find((o) => o.id === selection);
                        if (option) {
                            selectedModifiers.push({
                                groupId: group.id,
                                groupName: group.name,
                                optionId: option.id,
                                name: option.name,
                                price: option.price || 0,
                            });
                        }
                    }
                }
            }
        }

        addItem(product, quantity, selectedModifiers, notes);
        showToast('Producto agregado al carrito');
    };

    const handleGoToCheckout = () => {
        openCart();
        router.visit(`/${tenant.slug}/checkout`);
    };

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER: Product not found state
    // ═══════════════════════════════════════════════════════════════════════
    if (!product) {
        return (
            <StoreLayout>
                <Head title="Producto no encontrado" />
                <div className="max-w-7xl mx-auto px-4 py-16 text-center">
                    <AlertCircle className="h-16 w-16 mx-auto text-gray-300 mb-4" />
                    <p className="text-gray-500 text-lg">Producto no encontrado.</p>
                    <Link
                        href={`/${tenant.slug}`}
                        className="mt-4 inline-flex items-center gap-2 text-blue-600 hover:underline"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Volver al catálogo
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    // Check if product is out of stock (considering cart)
    const isOutOfStock = product.manage_stock && maxQuantity <= 0;

    return (
        <StoreLayout>
            {/* ═══════════════════════════════════════════════════════════════
                HEAD: SEO & OpenGraph Meta Tags
            ═══════════════════════════════════════════════════════════════ */}
            <Head>
                <title>{seoData.title} | {tenant.name}</title>

                {/* Standard SEO Meta */}
                <meta name="description" content={seoData.description} />

                {/* OpenGraph for Facebook/WhatsApp */}
                <meta property="og:type" content="product" />
                <meta property="og:title" content={seoData.title} />
                <meta property="og:description" content={seoData.description} />
                <meta property="og:image" content={seoData.image} />
                <meta property="og:site_name" content={tenant.name || 'Conecta HN'} />

                {/* Product Price Metadata (Commerce) */}
                <meta property="product:price:amount" content={String(seoData.price)} />
                <meta property="product:price:currency" content="HNL" />

                {/* Twitter Card */}
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={seoData.title} />
                <meta name="twitter:description" content={seoData.description} />
                <meta name="twitter:image" content={seoData.image} />
            </Head>

            {/* Accessible Toast Notification */}
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

            <div className="max-w-7xl mx-auto px-4 py-6">
                {/* Back Link */}
                <Link
                    href={`/${tenant.slug}`}
                    className="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6"
                >
                    <ArrowLeft className="h-4 w-4 mr-2" />
                    Volver al catálogo
                </Link>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {/* Product Image - with self-healing fallback */}
                    <div className="aspect-square rounded-2xl overflow-hidden bg-gray-100">
                        {product.image ? (
                            <ImageWithFallback
                                src={product.image}
                                fallbackSrc="/images/placeholder.png"
                                alt={product.name}
                                className="w-full h-full object-cover"
                                loading="lazy"
                            />
                        ) : (
                            <div
                                className="w-full h-full flex items-center justify-center"
                                style={{ backgroundColor: `${primaryColor}15` }}
                            >
                                <ShoppingCart
                                    className="h-20 w-20 opacity-30"
                                    style={{ color: primaryColor }}
                                />
                            </div>
                        )}
                    </div>

                    {/* Product Configuration */}
                    <div className="space-y-6">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">
                                {product.name}
                            </h1>
                            <div className="flex items-center gap-3 mt-2">
                                {/* Discount price display */}
                                {product.price_before_discount &&
                                    product.price_before_discount > product.price && (
                                        <span className="text-lg text-gray-400 line-through">
                                            {formatCurrency(product.price_before_discount)}
                                        </span>
                                    )}
                                <p
                                    className="text-2xl font-bold"
                                    style={{ color: primaryColor }}
                                >
                                    {formatCurrency(product.price)}
                                </p>
                            </div>
                        </div>

                        {product.description && (
                            <p className="text-gray-600">{product.description}</p>
                        )}

                        {/* Stock indicator - shows available considering cart */}
                        {product.manage_stock && (
                            <p className="text-sm text-gray-500">
                                {isOutOfStock ? (
                                    <span className="text-red-500 font-medium">
                                        Agotado{existingCartQty > 0 && ' (tienes el máximo en tu carrito)'}
                                    </span>
                                ) : (
                                    <>
                                        {maxQuantity} disponibles
                                        {existingCartQty > 0 && (
                                            <span className="text-gray-400">
                                                {' '}(ya tienes {existingCartQty} en tu carrito)
                                            </span>
                                        )}
                                    </>
                                )}
                            </p>
                        )}

                        {/* Modifier Groups */}
                        {product.modifier_groups?.map((group) => (
                            <div key={group.id} className="border-t border-gray-200 pt-6">
                                {group.max_selections === 1 ? (
                                    <ModifierGroupRadio
                                        group={group}
                                        selectedOption={modifiers[group.id]}
                                        onSelect={(optionId) =>
                                            handleRadioSelect(group.id, optionId)
                                        }
                                        primaryColor={primaryColor}
                                    />
                                ) : (
                                    <ModifierGroupCheckbox
                                        group={group}
                                        selectedOptions={modifiers[group.id] || []}
                                        onToggle={(optionId) =>
                                            handleCheckboxToggle(
                                                group.id,
                                                optionId,
                                                group.max_selections
                                            )
                                        }
                                        primaryColor={primaryColor}
                                    />
                                )}
                            </div>
                        ))}

                        {/* Notes */}
                        <div className="border-t border-gray-200 pt-6">
                            <label
                                htmlFor="notes"
                                className="block font-semibold text-gray-900 mb-2"
                            >
                                Notas adicionales (opcional)
                            </label>
                            <textarea
                                id="notes"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                rows={3}
                                className="w-full rounded-lg border border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-offset-2"
                                style={{ '--tw-ring-color': primaryColor }}
                                placeholder="Instrucciones especiales..."
                                aria-label="Notas adicionales para el producto"
                            />
                        </div>

                        {/* Quantity & Add to Cart */}
                        <div className="border-t border-gray-200 pt-6 space-y-4">
                            {/* Quantity Selector with clamping */}
                            <div className="flex items-center justify-between">
                                <span className="font-semibold text-gray-900">Cantidad</span>
                                <div className="flex items-center gap-4">
                                    <button
                                        type="button"
                                        onClick={handleDecrement}
                                        disabled={quantity <= 1 || isOutOfStock}
                                        className={`p-2 rounded-full border border-gray-300 transition-colors
                                            ${quantity <= 1 || isOutOfStock
                                                ? 'opacity-50 cursor-not-allowed'
                                                : 'hover:bg-gray-100'
                                            }`}
                                        aria-label="Reducir cantidad"
                                    >
                                        <Minus className="h-4 w-4" />
                                    </button>
                                    <span className="text-xl font-bold w-8 text-center">
                                        {quantity}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={handleIncrement}
                                        disabled={quantity >= maxQuantity || isOutOfStock}
                                        className={`p-2 rounded-full border border-gray-300 transition-colors
                                            ${quantity >= maxQuantity || isOutOfStock
                                                ? 'opacity-50 cursor-not-allowed'
                                                : 'hover:bg-gray-100'
                                            }`}
                                        aria-label="Aumentar cantidad"
                                    >
                                        <Plus className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            {/* Stock warning when at max */}
                            {product.manage_stock && quantity >= maxQuantity && !isOutOfStock && (
                                <p className="text-sm text-amber-600">
                                    Cantidad máxima disponible alcanzada.
                                </p>
                            )}

                            {/* Validation Warning */}
                            {!isSelectionComplete && (
                                <div className="flex items-center gap-2 p-3 bg-amber-50 text-amber-700 rounded-lg">
                                    <AlertCircle className="h-5 w-5 flex-shrink-0" />
                                    <p className="text-sm">
                                        Selecciona las opciones requeridas para continuar.
                                    </p>
                                </div>
                            )}

                            {/* Add to Cart Button */}
                            <button
                                type="button"
                                onClick={handleAddToCart}
                                disabled={!isSelectionComplete || isOutOfStock}
                                className={`w-full flex items-center justify-center gap-2 px-6 py-4 text-white font-bold rounded-xl transition-all
                                    ${(!isSelectionComplete || isOutOfStock)
                                        ? 'opacity-50 cursor-not-allowed grayscale'
                                        : 'hover:opacity-90 hover:scale-[1.02]'
                                    }`}
                                style={{ backgroundColor: primaryColor }}
                                aria-label="Agregar al carrito"
                            >
                                <ShoppingCart className="h-5 w-5" />
                                {isOutOfStock ? 'Agotado' : `Agregar ${formatCurrency(calculatedPrice)}`}
                            </button>

                            {/* Go to Checkout Button */}
                            <button
                                type="button"
                                onClick={handleGoToCheckout}
                                className="w-full text-center py-3 text-gray-600 hover:text-gray-900 font-medium transition-colors"
                            >
                                Ir al carrito →
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </StoreLayout>
    );
}
