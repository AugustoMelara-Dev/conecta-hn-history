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

import { useState, useMemo, memo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { toast as sonnerToast } from 'sonner';
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
import { ActionableAlert, EmptyState } from '@/Components/Feedback/UXStates';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Separator } from '@/Components/ui/separator';
import { Textarea } from '@/Components/ui/textarea';
import { cn } from '@/lib/utils';

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
    primaryColor,
    invalid = false,
}) {
    const selectedValue = selectedOption === undefined || selectedOption === null
        ? undefined
        : String(selectedOption);

    const handleValueChange = (value) => {
        const matchedOption = group.options?.find((option) => String(option.id) === value);

        if (matchedOption) {
            onSelect(matchedOption.id);
        }
    };

    return (
        <FieldGroup
            className="gap-3"
            data-invalid={invalid ? 'true' : undefined}
            style={{
                '--product-accent': primaryColor,
                '--product-accent-soft': `${primaryColor}12`,
            }}
        >
            <div className="flex items-center justify-between gap-3">
                <h3 className="font-semibold text-foreground">{group.name}</h3>
                {group.required && (
                    <Badge variant="outline" className="shrink-0">
                        Requerido
                    </Badge>
                )}
            </div>

            <RadioGroup
                value={selectedValue}
                onValueChange={handleValueChange}
                aria-label={group.name}
                aria-invalid={invalid ? 'true' : undefined}
                aria-describedby={invalid ? `modifier-group-${group.id}-error` : undefined}
            >
                {group.options?.map((option) => {
                    const optionValue = String(option.id);
                    const checked = selectedValue === optionValue;
                    const radioId = `modifier-radio-${group.id}-${option.id}`;

                    return (
                        <label
                            key={option.id}
                            htmlFor={radioId}
                            className={cn(
                                'flex cursor-pointer items-center justify-between gap-3 rounded-lg border p-4 transition-colors',
                                'focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
                                checked
                                    ? 'border-[color:var(--product-accent)] bg-[color:var(--product-accent-soft)]'
                                    : 'border-border hover:bg-muted',
                            )}
                        >
                            <div className="flex min-w-0 items-center gap-3">
                                <RadioGroupItem
                                    id={radioId}
                                    value={optionValue}
                                    data-testid={`product-modifier-radio-${group.id}-${option.id}`}
                                    className="shrink-0 data-[state=checked]:border-[color:var(--product-accent)] data-[state=checked]:text-[color:var(--product-accent)]"
                                />
                                <span className="min-w-0 font-medium text-foreground">
                                    {option.name}
                                </span>
                            </div>
                            {option.price > 0 && (
                                <span className="shrink-0 text-sm text-muted-foreground">
                                    + {formatCurrency(option.price)}
                                </span>
                            )}
                        </label>
                    );
                })}
            </RadioGroup>
        </FieldGroup>
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
    primaryColor,
    invalid = false,
}) {
    return (
        <FieldGroup
            className="gap-3"
            data-invalid={invalid ? 'true' : undefined}
            style={{
                '--product-accent': primaryColor,
                '--product-accent-soft': `${primaryColor}12`,
            }}
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2">
                    <h3 className="font-semibold text-foreground">{group.name}</h3>
                    {group.required && (
                        <Badge variant="outline" className="shrink-0">
                            Requerido
                        </Badge>
                    )}
                </div>
                <span className="shrink-0 text-sm text-muted-foreground">
                    Máximo: {group.max_selections}
                </span>
            </div>

            <div
                className="grid gap-2"
                role="group"
                aria-label={group.name}
                aria-invalid={invalid ? 'true' : undefined}
                aria-describedby={invalid ? `modifier-group-${group.id}-error` : undefined}
            >
                {group.options?.map((option) => {
                    const isSelected = selectedOptions.includes(option.id);
                    const isDisabled =
                        !isSelected && selectedOptions.length >= group.max_selections;
                    const checkboxId = `modifier-${group.id}-${option.id}`;

                    return (
                        <label
                            key={option.id}
                            htmlFor={checkboxId}
                            className={cn(
                                'flex cursor-pointer items-center justify-between gap-3 rounded-lg border p-4 transition-colors',
                                'focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
                                isSelected
                                    ? 'border-[color:var(--product-accent)] bg-[color:var(--product-accent-soft)]'
                                    : 'border-border hover:bg-muted',
                                isDisabled && 'cursor-not-allowed opacity-50',
                            )}
                        >
                            <div className="flex min-w-0 items-center">
                                <Checkbox
                                    id={checkboxId}
                                    checked={isSelected}
                                    onCheckedChange={() => onToggle(option.id)}
                                    disabled={isDisabled}
                                    data-testid={`product-modifier-checkbox-${group.id}-${option.id}`}
                                    aria-label={`${option.name}${option.price > 0 ? `, + ${formatCurrency(option.price)}` : ''}`}
                                    className="mr-3"
                                />
                                <span className="min-w-0 font-medium text-foreground">
                                    {option.name}
                                </span>
                            </div>
                            {option.price > 0 && (
                                <span className="shrink-0 text-sm text-muted-foreground">
                                    + {formatCurrency(option.price)}
                                </span>
                            )}
                        </label>
                    );
                })}
            </div>
        </FieldGroup>
    );
});

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

    // Cart store - need items to check existing quantity
    const { items, addItem, openCart } = useCartStore();

    // ═══════════════════════════════════════════════════════════════════════
    // SEO: Prepare OpenGraph metadata with ABSOLUTE URLs
    // ═══════════════════════════════════════════════════════════════════════
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
        const url = `${baseUrl}/t/${tenant?.slug}/product/${product?.id}`;

        const price = product?.price || 0;

        return { title, description, image, url, price };
    }, [product?.name, product?.description, product?.image, product?.price, product?.id, tenant?.logo, tenant?.slug]);

    // ═══════════════════════════════════════════════════════════════════════
    // Toast feedback now uses the app-level Sonner toaster.
    // ═══════════════════════════════════════════════════════════════════════
    // ═══════════════════════════════════════════════════════════════════════
    // Calculate existing quantity in cart for this product
    // ═══════════════════════════════════════════════════════════════════════
    const existingCartQty = useMemo(() => {
        if (!product?.id) return 0;
        const cartItem = items.find((item) => item.productId === product.id);
        return cartItem?.quantity || 0;
    }, [items, product?.id]);

    // ═══════════════════════════════════════════════════════════════════════
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
    // ═══════════════════════════════════════════════════════════════════════
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
    // QUANTITY HANDLERS: With input clamping using stockInfo
    // ═══════════════════════════════════════════════════════════════════════
    const handleIncrement = () => {
        setQuantity((q) => Math.min(stockInfo.maxQuantityForSelector, q + 1));
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

    const missingRequiredGroups = useMemo(() => {
        if (!product?.modifier_groups) return [];

        return product.modifier_groups.filter((group) => {
            if (!group.required) return false;

            const selection = modifiers[group.id];

            if (group.max_selections === 1) {
                return !selection;
            }

            return !selection || selection.length === 0;
        });
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
    // TOAST HELPER
    // ═══════════════════════════════════════════════════════════════════════
    const showToast = (message, type = 'success') => {
        const notify = type === 'error'
            ? sonnerToast.error
            : type === 'warning'
                ? sonnerToast.warning
                : sonnerToast.success;

        notify(message);
    };

    // ═══════════════════════════════════════════════════════════════════════
    // CART INJECTION
    // ═══════════════════════════════════════════════════════════════════════
    const handleAddToCart = () => {
        if (!isSelectionComplete) return;

        // Guard: Stock limit already reached
        if (stockInfo.isStockLimitReached) {
            showToast('Has alcanzado el límite de unidades disponibles', 'error');
            return;
        }

        // Additional stock validation before adding
        if (product?.manage_stock) {
            const projectedTotal = existingCartQty + quantity;
            if (projectedTotal > product.stock_quantity) {
                showToast(
                    `Solo puedes agregar ${stockInfo.realAvailable} unidades más`,
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
        router.visit(`/t/${tenant.slug}/checkout`);
    };

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER: Product not found state
    // ═══════════════════════════════════════════════════════════════════════
    if (!product) {
        return (
            <StoreLayout>
                <Head title="Producto no encontrado" />
                <div className="mx-auto max-w-3xl px-4 py-16">
                    <EmptyState
                        icon={AlertCircle}
                        eyebrow="Producto"
                        title="Producto no encontrado"
                        description="Vuelve al catálogo del negocio para revisar las opciones disponibles."
                        actionLabel="Volver al catálogo"
                        actionHref={tenant.slug ? `/t/${tenant.slug}` : '/'}
                        testId="product-empty-state"
                    />
                </div>
            </StoreLayout>
        );
    }

    // Destructure stockInfo for cleaner render logic
    const { isOutOfStock, isStockLimitReached, maxQuantityForSelector, realAvailable } = stockInfo;

    return (
        <StoreLayout>
            {/* ═══════════════════════════════════════════════════════════════
                HEAD: SEO & OpenGraph Meta Tags with ABSOLUTE URLs
            ═══════════════════════════════════════════════════════════════ */}
            <Head title={`${seoData.title} | ${tenant.name || 'Vito Business OS'}`}>
                {/* Standard SEO Meta */}
                <meta name="description" content={seoData.description} />

                {/* OpenGraph for Facebook/WhatsApp */}
                <meta property="og:type" content="product" />
                <meta property="og:title" content={seoData.title} />
                <meta property="og:description" content={seoData.description} />
                <meta property="og:image" content={seoData.image} />
                <meta property="og:url" content={seoData.url} />
                <meta property="og:site_name" content={tenant.name || 'Vito Business OS'} />

                {/* Product Price Metadata (Commerce) */}
                <meta property="product:price:amount" content={String(seoData.price)} />
                <meta property="product:price:currency" content="HNL" />

                {/* Twitter Card */}
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={seoData.title} />
                <meta name="twitter:description" content={seoData.description} />
                <meta name="twitter:image" content={seoData.image} />
            </Head>

            <div
                className="mx-auto max-w-7xl px-4 py-6"
                style={{
                    '--product-accent': primaryColor,
                    '--product-accent-soft': `${primaryColor}14`,
                }}
            >
                {/* Back Link */}
                <Link
                    href={tenant.slug ? `/t/${tenant.slug}` : '/'}
                    className="mb-6 inline-flex items-center gap-2 rounded-md text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <ArrowLeft className="h-4 w-4" data-icon aria-hidden="true" />
                    Volver al catálogo
                </Link>

                <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
                    {/* Product Image - with self-healing fallback */}
                    <Card className="aspect-square overflow-hidden rounded-lg bg-muted">
                        {product.image ? (
                            <ImageWithFallback
                                src={product.image}
                                alt={product.name}
                                className="w-full h-full object-cover"
                                loading="eager"
                                fadeIn
                            />
                        ) : (
                            <div
                                className="w-full h-full flex items-center justify-center"
                                style={{ backgroundColor: 'var(--product-accent-soft)' }}
                            >
                                <ShoppingCart
                                    className="h-20 w-20 opacity-40 text-[color:var(--product-accent)]"
                                    aria-hidden="true"
                                />
                            </div>
                        )}
                    </Card>

                    {/* Product Configuration */}
                    <div className="grid gap-6">
                        <Card>
                            <CardHeader className="gap-3">
                                <CardTitle className="text-3xl">
                                    {product.name}
                                </CardTitle>
                                <div className="flex flex-wrap items-center gap-3">
                                {/* Discount price display */}
                                {product.price_before_discount &&
                                    product.price_before_discount > product.price && (
                                        <span className="text-lg text-muted-foreground line-through">
                                            {formatCurrency(product.price_before_discount)}
                                        </span>
                                    )}
                                <p
                                    className="text-2xl font-bold text-[color:var(--product-accent)]"
                                    style={{ color: primaryColor }}
                                >
                                    {formatCurrency(product.price)}
                                </p>
                            </div>
                            </CardHeader>

                        {product.description && (
                            <CardContent className="pt-0">
                                <p className="text-muted-foreground">{product.description}</p>
                            </CardContent>
                        )}

                        {/* Stock indicator - shows available considering cart */}
                        {product.manage_stock && (
                            <CardContent className={product.description ? 'pt-0' : ''}>
                                {isOutOfStock ? (
                                    <Badge variant="destructive">
                                        Agotado{existingCartQty > 0 && ' (tienes el máximo en tu carrito)'}
                                    </Badge>
                                ) : isStockLimitReached ? (
                                    <Badge variant="secondary">
                                        Límite alcanzado (tienes {existingCartQty} en tu carrito)
                                    </Badge>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {realAvailable} disponibles
                                        {existingCartQty > 0 && (
                                            <span>
                                                {' '}(ya tienes {existingCartQty} en tu carrito)
                                            </span>
                                        )}
                                    </p>
                                )}
                            </CardContent>
                        )}
                        </Card>

                        {/* Modifier Groups */}
                        {missingRequiredGroups.length > 0 && (
                            <Alert variant="warning" role="alert" data-testid="product-required-options-summary">
                                <AlertCircle className="h-4 w-4" data-icon aria-hidden="true" />
                                <AlertTitle>Faltan opciones requeridas</AlertTitle>
                                <AlertDescription>
                                    <p>Completa estos grupos antes de agregar el producto:</p>
                                    <ul className="mt-2 grid list-disc gap-1 pl-5">
                                        {missingRequiredGroups.map((group) => (
                                            <li key={group.id}>{group.name}</li>
                                        ))}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        )}

                        {product.modifier_groups?.map((group) => (
                            <Card key={group.id}>
                                <CardContent className="p-5">
                                {group.max_selections === 1 ? (
                                    <ModifierGroupRadio
                                        group={group}
                                        selectedOption={modifiers[group.id]}
                                        onSelect={(optionId) =>
                                            handleRadioSelect(group.id, optionId)
                                        }
                                        primaryColor={primaryColor}
                                        invalid={missingRequiredGroups.some((missingGroup) => missingGroup.id === group.id)}
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
                                        invalid={missingRequiredGroups.some((missingGroup) => missingGroup.id === group.id)}
                                    />
                                )}
                                {missingRequiredGroups.some((missingGroup) => missingGroup.id === group.id) && (
                                    <FieldDescription
                                        id={`modifier-group-${group.id}-error`}
                                        role="alert"
                                        className="mt-3 text-destructive"
                                    >
                                        Selecciona al menos una opción para continuar.
                                    </FieldDescription>
                                )}
                                </CardContent>
                            </Card>
                        ))}

                        {/* Notes */}
                        <Card>
                            <CardContent className="p-5">
                                <Field>
                                    <FieldLabel htmlFor="notes">Notas adicionales</FieldLabel>
                                    <FieldDescription>
                                        Opcional. Puedes agregar instrucciones especiales para este producto.
                                    </FieldDescription>
                                    <Textarea
                                        id="notes"
                                        value={notes}
                                        onChange={(e) => setNotes(e.target.value)}
                                        rows={3}
                                        placeholder="Instrucciones especiales..."
                                        aria-label="Notas adicionales para el producto"
                                        data-testid="product-notes-input"
                                    />
                                </Field>
                            </CardContent>
                        </Card>

                        {/* Quantity & Add to Cart */}
                        <Card>
                            <CardContent className="grid gap-4 p-5">
                            {/* Quantity Selector with clamping */}
                            <div className="flex items-center justify-between">
                                <span className="font-semibold text-foreground">Cantidad</span>
                                <div className="flex items-center gap-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={handleDecrement}
                                        disabled={quantity <= 1 || isOutOfStock}
                                        aria-label="Reducir cantidad"
                                        data-testid="product-quantity-decrease"
                                    >
                                        <Minus className="h-4 w-4" data-icon aria-hidden="true" />
                                    </Button>
                                    <span className="w-8 text-center text-xl font-bold" aria-live="polite">
                                        {quantity}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={handleIncrement}
                                        disabled={quantity >= maxQuantityForSelector || isOutOfStock || isStockLimitReached}
                                        aria-label="Aumentar cantidad"
                                        data-testid="product-quantity-increase"
                                    >
                                        <Plus className="h-4 w-4" data-icon aria-hidden="true" />
                                    </Button>
                                </div>
                            </div>

                            {/* Stock warning when at max */}
                            {product.manage_stock && quantity >= maxQuantityForSelector && !isOutOfStock && !isStockLimitReached && (
                                <ActionableAlert
                                    icon={AlertCircle}
                                    tone="warning"
                                    title="Cantidad máxima alcanzada"
                                    testId="product-stock-warning"
                                >
                                    Ya estás en la cantidad disponible para este producto.
                                </ActionableAlert>
                            )}

                            {/* Validation Warning */}
                            {!isSelectionComplete && (
                                <ActionableAlert
                                    icon={AlertCircle}
                                    tone="warning"
                                    title="Faltan opciones requeridas"
                                    testId="product-required-options-warning"
                                >
                                    Selecciona las opciones requeridas para continuar.
                                </ActionableAlert>
                            )}

                            {/* Add to Cart Button - CRITICAL: Uses isStockLimitReached to prevent availability illusion */}
                            <Button
                                type="button"
                                onClick={handleAddToCart}
                                disabled={!isSelectionComplete || isOutOfStock || isStockLimitReached}
                                className="w-full text-white hover:opacity-90"
                                style={{ backgroundColor: primaryColor }}
                                aria-label="Agregar al carrito"
                                data-testid="product-add-to-cart"
                            >
                                <ShoppingCart className="h-5 w-5" data-icon aria-hidden="true" />
                                {isOutOfStock
                                    ? 'Agotado'
                                    : isStockLimitReached
                                        ? 'Límite alcanzado'
                                        : `Agregar ${formatCurrency(calculatedPrice)}`
                                }
                            </Button>

                            <Separator />

                            {/* Go to Checkout Button */}
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={handleGoToCheckout}
                                className="w-full"
                                data-testid="product-go-cart"
                            >
                                Ir al carrito
                            </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </StoreLayout>
    );
}
