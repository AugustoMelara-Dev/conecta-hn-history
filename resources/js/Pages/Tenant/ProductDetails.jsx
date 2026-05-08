/**
 * ProductDetails.jsx - Product Configuration Engine (ENTERPRISE v4)
 *
 * ARCHITECTURE:
 * - Modifier state management with validation
 * - RadioGroup for single-select, Checkboxes for multi-select
 * - Quantity clamping with stock limits (CONSIDERS CART QUANTITY)
 * - WCAG-compliant accessible notifications
 * - OpenGraph meta tags with ABSOLUTE URLs for social sharing
 * - Self-healing images with fallback
 *
 * SECURITY:
 * - Prevents quantity exceeding stock via input clamping
 * - Validates all required modifiers before cart injection
 * - Accounts for existing cart items when calculating max quantity
 *
 * SEO:
 * - Dynamic og:title, og:description, og:image (ABSOLUTE URLS)
 * - og:url canonical for correct sharing
 * - Product price metadata for commerce indexing
 */

import { useState, useMemo, useRef, useEffect, memo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ShoppingCart,
    Minus,
    Plus,
    AlertCircle,
} from 'lucide-react';
import StoreLayout from '@/Layouts/StoreLayout';
import useCartStore from '@/Stores/useCartStore';
import useTenantContext from '@/Hooks/useTenantContext';
import { formatCurrency } from '@/Utils/currency';
import ImageWithFallback from '@/Components/ImageWithFallback';
import Toast from '@/Components/Toast';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Field } from '@/Components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Separator } from '@/Components/ui/separator';
import { Textarea } from '@/Components/ui/textarea';

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
 * Memoized for performance with many options
 */
const ModifierGroupRadio = memo(function ModifierGroupRadio({
    group,
    selectedOption,
    onSelect,
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between gap-3">
                    <CardTitle className="text-base">{group.name}</CardTitle>
                    {group.required ? <Badge variant="secondary">Requerido</Badge> : null}
                </div>
            </CardHeader>
            <CardContent>
                <RadioGroup
                    value={selectedOption == null ? undefined : String(selectedOption)}
                    onValueChange={(value) => {
                        const option = group.options?.find((item) => String(item.id) === value);
                        onSelect(option?.id ?? value);
                    }}
                    aria-label={group.name}
                    className="gap-3"
                >
                    {group.options?.map((option) => (
                        <label
                            key={option.id}
                            className="flex cursor-pointer items-center justify-between gap-4 rounded-lg border bg-card p-4"
                        >
                            <span className="flex items-center gap-3">
                                <RadioGroupItem value={String(option.id)} />
                                <span className="font-medium text-foreground">{option.name}</span>
                            </span>
                            {option.price > 0 ? (
                                <span className="text-sm text-muted-foreground">
                                    + {formatCurrency(option.price)}
                                </span>
                            ) : null}
                        </label>
                    ))}
                </RadioGroup>
            </CardContent>
        </Card>
    );
});

/**
 * ModifierGroupCheckbox - Multi-selection modifier group
 * Memoized for performance
 */
const ModifierGroupCheckbox = memo(function ModifierGroupCheckbox({
    group,
    selectedOptions = [],
    onToggle,
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between gap-3">
                    <CardTitle className="text-base">{group.name}</CardTitle>
                    <div className="flex items-center gap-2">
                        {group.required ? <Badge variant="secondary">Requerido</Badge> : null}
                        <Badge variant="outline">Maximo: {group.max_selections}</Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col gap-3" role="group" aria-label={group.name}>
                    {group.options?.map((option) => {
                        const isSelected = selectedOptions.includes(option.id);
                        const isDisabled =
                            !isSelected && selectedOptions.length >= group.max_selections;

                        return (
                            <Field
                                key={option.id}
                                data-disabled={isDisabled}
                                className="rounded-lg border bg-card p-4 data-[disabled=true]:opacity-50"
                            >
                                <label className="flex cursor-pointer items-center justify-between gap-4">
                                    <span className="flex items-center gap-3">
                                        <Checkbox
                                            checked={isSelected}
                                            disabled={isDisabled}
                                            onCheckedChange={() => onToggle(option.id)}
                                            aria-label={option.name}
                                        />
                                        <span className="font-medium text-foreground">{option.name}</span>
                                    </span>
                                    {option.price > 0 ? (
                                        <span className="text-sm text-muted-foreground">
                                            + {formatCurrency(option.price)}
                                        </span>
                                    ) : null}
                                </label>
                            </Field>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
});

/**
 * ProductDetails - Main Product Configuration Page
 */
export default function ProductDetails({ product }) {
    // Use centralized tenant context hook
    const { tenant } = useTenantContext();
    // STATE: Modifier selections & quantity
    const [modifiers, setModifiers] = useState({});
    const [quantity, setQuantity] = useState(1);
    const [notes, setNotes] = useState('');
    const [toast, setToast] = useState(null);
    const toastTimeoutRef = useRef(null);

    // Cart store - need items to check existing quantity
    const { items, addItem, openCart } = useCartStore();
    // SEO: Prepare OpenGraph metadata with ABSOLUTE URLs
    const seoData = useMemo(() => {
        const title = product?.name || 'Producto';
        const description = truncateDescription(
            product?.description || `Descubre ${title} en nuestra tienda`
        );

        // Build absolute URLs for og:image and og:url
        const baseUrl = typeof window !== 'undefined' ? window.location.origin : '';

        // Image: prefer product image, then tenant logo, then default
        const imageRaw = product?.image || tenant?.logo || '/images/og-default.svg';
        const image = imageRaw.startsWith('http') ? imageRaw : `${baseUrl}${imageRaw}`;

        // Canonical URL for the product
        const url = `${baseUrl}/${tenant?.slug}/product/${product?.id}`;

        const price = product?.price || 0;

        return { title, description, image, url, price };
    }, [product?.name, product?.description, product?.image, product?.price, product?.id, tenant?.logo, tenant?.slug]);
    // CLEANUP: Clear toast timeout on unmount
    useEffect(() => {
        return () => {
            if (toastTimeoutRef.current) {
                clearTimeout(toastTimeoutRef.current);
            }
        };
    }, []);
    // Calculate existing quantity in cart for this product
    const existingCartQty = useMemo(() => {
        if (!product?.id) return 0;
        const cartItem = items.find((item) => item.productId === product.id);
        return cartItem?.quantity || 0;
    }, [items, product?.id]);
    // STOCK LOGIC: Separated from UI quantity selector
    // 
    // CRITICAL FIX: Previously maxQuantity used Math.max(1, available) which
    // masked the real stock state, causing "availability illusion" where
    // the button stayed enabled even when stock was exhausted.
    // 
    // Now we have:
    // - realAvailable: Actual units available (can be 0 or negative)
    // - isStockLimitReached: True when user has max possible in cart
    // - maxQuantityForSelector: Min 1 for UI, but button uses isStockLimitReached
    const stockInfo = useMemo(() => {
        if (!product?.manage_stock) {
            return {
                realAvailable: 99,
                isStockLimitReached: false,
                maxQuantityForSelector: 99,
                isOutOfStock: false,
            };
        }

        const totalStock = product.stock_quantity || 0;
        const realAvailable = totalStock - existingCartQty;

        return {
            realAvailable,
            isStockLimitReached: realAvailable <= 0,
            // UI selector needs min 1 to function, but we disable button separately
            maxQuantityForSelector: Math.max(1, realAvailable),
            // Product is completely out of stock (not just limit reached for this user)
            isOutOfStock: totalStock <= 0,
        };
    }, [product?.manage_stock, product?.stock_quantity, existingCartQty]);
    // MODIFIER HANDLERS
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
    // QUANTITY HANDLERS: With input clamping using stockInfo
    const handleIncrement = () => {
        setQuantity((q) => Math.min(stockInfo.maxQuantityForSelector, q + 1));
    };

    const handleDecrement = () => {
        setQuantity((q) => Math.max(1, q - 1));
    };
    // GATEKEEPER: Validation for required modifiers
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
    // PRICE CALCULATION: Base + modifiers * quantity
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
    // TOAST HELPER with proper cleanup
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

    const hideToast = () => {
        if (toastTimeoutRef.current) {
            clearTimeout(toastTimeoutRef.current);
            toastTimeoutRef.current = null;
        }
        setToast(null);
    };
    // CART INJECTION
    const handleAddToCart = () => {
        if (!isSelectionComplete) return;

        // Guard: Stock limit already reached
        if (stockInfo.isStockLimitReached) {
            showToast('Has alcanzado el limite de unidades disponibles', 'error');
            return;
        }

        // Additional stock validation before adding
        if (product?.manage_stock) {
            const projectedTotal = existingCartQty + quantity;
            if (projectedTotal > product.stock_quantity) {
                showToast(
                    `Solo puedes agregar ${stockInfo.realAvailable} unidades mas`,
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
        showToast('Producto agregado al carrito', 'success');
    };

    const handleGoToCheckout = () => {
        openCart();
        router.visit(`/${tenant.slug}/checkout`);
    };
    // RENDER: Product not found state
    if (!product) {
        return (
            <StoreLayout>
                <Head title="Producto no encontrado" />
                <div className="max-w-7xl mx-auto px-4 py-16 text-center" data-testid="product-detail-empty">
                    <AlertCircle className="mx-auto mb-4 size-16 text-muted-foreground" aria-hidden="true" />
                    <p className="text-lg text-muted-foreground">Producto no encontrado.</p>
                    <Button asChild variant="link" className="mt-4">
                        <Link href={`/${tenant.slug}`}>
                            <ArrowLeft data-icon="inline-start" aria-hidden="true" />
                            Volver al catalogo
                        </Link>
                    </Button>
                </div>
            </StoreLayout>
        );
    }

    // Destructure stockInfo for cleaner render logic
    const { isOutOfStock, isStockLimitReached, maxQuantityForSelector, realAvailable } = stockInfo;

    return (
        <StoreLayout>
            <Head>
                <title>{seoData.title} | {tenant.name}</title>

                {/* Standard SEO Meta */}
                <meta name="description" content={seoData.description} />

                {/* OpenGraph for Facebook/WhatsApp */}
                <meta property="og:type" content="product" />
                <meta property="og:title" content={seoData.title} />
                <meta property="og:description" content={seoData.description} />
                <meta property="og:image" content={seoData.image} />
                <meta property="og:url" content={seoData.url} />
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

            {/* Accessible Toast Notification - Centralized component */}
            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={hideToast}
                />
            )}

            <div className="max-w-7xl mx-auto px-4 py-6" data-testid="product-detail-page">
                {/* Back Link */}
                <Button asChild variant="ghost" className="mb-6 justify-start px-0">
                    <Link href={`/${tenant.slug}`}>
                        <ArrowLeft data-icon="inline-start" aria-hidden="true" />
                        Volver al catalogo
                    </Link>
                </Button>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {/* Product Image - with self-healing fallback */}
                    <div className="aspect-square overflow-hidden rounded-2xl bg-muted" data-testid="product-detail-image">
                        {product.image ? (
                            <ImageWithFallback
                                src={product.image}
                                alt={product.name}
                                className="w-full h-full object-cover"
                                loading="eager"
                                fadeIn
                            />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center bg-primary/10">
                                <ShoppingCart
                                    className="size-20 text-primary opacity-30"
                                    aria-hidden="true"
                                />
                            </div>
                        )}
                    </div>

                    {/* Product Configuration */}
                    <div className="flex flex-col gap-6" data-testid="product-detail-summary">
                        <div data-testid="product-detail-heading">
                            <h1 className="text-3xl font-bold text-foreground">
                                {product.name}
                            </h1>
                            <div className="flex items-center gap-3 mt-2">
                                {/* Discount price display */}
                                {product.price_before_discount &&
                                    product.price_before_discount > product.price && (
                                        <span className="text-lg text-muted-foreground line-through">
                                            {formatCurrency(product.price_before_discount)}
                                        </span>
                                    )}
                                <p className="text-2xl font-bold text-primary">
                                    {formatCurrency(product.price)}
                                </p>
                            </div>
                        </div>

                        {product.description && (
                            <p className="text-muted-foreground">{product.description}</p>
                        )}

                        {/* Stock indicator - shows available considering cart */}
                        {product.manage_stock && (
                            <p className="text-sm text-muted-foreground">
                                {isOutOfStock ? (
                                    <span className="font-medium text-destructive">
                                        Agotado{existingCartQty > 0 && ' (tienes el maximo en tu carrito)'}
                                    </span>
                                ) : isStockLimitReached ? (
                                    <span className="font-medium text-accent-foreground">
                                        Limite alcanzado (tienes {existingCartQty} en tu carrito)
                                    </span>
                                ) : (
                                    <>
                                        {realAvailable} disponibles
                                        {existingCartQty > 0 && (
                                            <span className="text-muted-foreground">
                                                {' '}(ya tienes {existingCartQty} en tu carrito)
                                            </span>
                                        )}
                                    </>
                                )}
                            </p>
                        )}

                        {/* Modifier Groups */}
                        {product.modifier_groups?.map((group) => (
                            <div key={group.id} className="flex flex-col gap-6 pt-2" data-testid="product-detail-option-group">
                                <Separator />
                                {group.max_selections === 1 ? (
                                    <ModifierGroupRadio
                                        group={group}
                                        selectedOption={modifiers[group.id]}
                                        onSelect={(optionId) =>
                                            handleRadioSelect(group.id, optionId)
                                        }
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
                                    />
                                )}
                            </div>
                        ))}

                        {/* Notes */}
                        <div className="flex flex-col gap-3 pt-2" data-testid="product-detail-notes">
                            <Separator />
                            <label
                                htmlFor="notes"
                                className="font-semibold text-foreground"
                            >
                                Notas adicionales (opcional)
                            </label>
                            <Textarea
                                id="notes"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                rows={3}
                                placeholder="Instrucciones especiales..."
                                aria-label="Notas adicionales para el producto"
                            />
                        </div>

                        {/* Quantity & Add to Cart */}
                        <div className="flex flex-col gap-4 pt-2" data-testid="product-detail-purchase">
                            <Separator />
                            {/* Quantity Selector with clamping */}
                            <div className="flex items-center justify-between" data-testid="product-detail-quantity">
                                <span className="font-semibold text-foreground">Cantidad</span>
                                <div className="flex items-center gap-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={handleDecrement}
                                        disabled={quantity <= 1 || isOutOfStock}
                                        aria-label="Reducir cantidad"
                                    >
                                        <Minus data-icon="inline-start" aria-hidden="true" />
                                    </Button>
                                    <span className="text-xl font-bold w-8 text-center">
                                        {quantity}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={handleIncrement}
                                        disabled={quantity >= maxQuantityForSelector || isOutOfStock || isStockLimitReached}
                                        aria-label="Aumentar cantidad"
                                    >
                                        <Plus data-icon="inline-start" aria-hidden="true" />
                                    </Button>
                                </div>
                            </div>

                            {/* Stock warning when at max */}
                            {product.manage_stock && quantity >= maxQuantityForSelector && !isOutOfStock && !isStockLimitReached && (
                                <Alert variant="warning">
                                    <AlertDescription>
                                        Cantidad maxima disponible alcanzada.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {/* Validation Warning */}
                            {!isSelectionComplete && (
                                <Alert variant="warning" className="flex items-center gap-2">
                                    <AlertCircle aria-hidden="true" />
                                    <AlertDescription>
                                        Selecciona las opciones requeridas para continuar.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {/* Add to Cart Button - CRITICAL: Uses isStockLimitReached to prevent availability illusion */}
                            <Button
                                type="button"
                                onClick={handleAddToCart}
                                disabled={!isSelectionComplete || isOutOfStock || isStockLimitReached}
                                className="w-full gap-2"
                                aria-label="Agregar al carrito"
                                data-testid="product-detail-add-to-cart"
                            >
                                <ShoppingCart data-icon="inline-start" aria-hidden="true" />
                                {isOutOfStock
                                    ? 'Agotado'
                                    : isStockLimitReached
                                        ? 'Limite alcanzado'
                                        : `Agregar ${formatCurrency(calculatedPrice)}`
                                }
                            </Button>

                            {/* Go to Checkout Button */}
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={handleGoToCheckout}
                                className="w-full"
                            >
                                Ir al carrito
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </StoreLayout>
    );
}
