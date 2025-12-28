/**
 * ProductDetails.jsx - Product Configuration Engine
 *
 * ARCHITECTURE:
 * - Modifier state management with validation
 * - RadioGroup for single-select, Checkboxes for multi-select
 * - Price reactivity per option
 * - Gatekeeper validation before cart injection
 *
 * VALIDATION:
 * Users cannot add to cart until ALL required modifier groups are satisfied.
 */

import { useState, useMemo, useEffect } from 'react';
import { Head, Link, usePage, router } from '@inertiajs/react';
import { RadioGroup } from '@headlessui/react';
import {
    ArrowLeft,
    ShoppingCart,
    Check,
    Minus,
    Plus,
    AlertCircle,
    CheckCircle,
} from 'lucide-react';
import StoreLayout from '@/Layouts/StoreLayout';
import useCartStore from '@/Stores/useCartStore';

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
 * ProductDetails - Main Product Configuration Page
 */
export default function ProductDetails({ tenant, product }) {
    const { props } = usePage();
    const tenantData = tenant || props.tenant || {};
    const primaryColor = tenantData.primary_color || '#000000';

    // ═══════════════════════════════════════════════════════════════════════
    // STATE: Modifier selections
    // ═══════════════════════════════════════════════════════════════════════
    const [modifiers, setModifiers] = useState({});
    const [quantity, setQuantity] = useState(1);
    const [notes, setNotes] = useState('');
    const [showToast, setShowToast] = useState(false);

    // Cart store
    const { addItem, openCart, initForTenant } = useCartStore();

    // Initialize cart for tenant
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
    // GATEKEEPER: Validation for required modifiers
    // ═══════════════════════════════════════════════════════════════════════
    const isSelectionComplete = useMemo(() => {
        if (!product?.modifier_groups) return true;

        for (const group of product.modifier_groups) {
            if (group.required) {
                const selection = modifiers[group.id];

                if (group.max_selections === 1) {
                    // Radio: Must have a selection
                    if (!selection) return false;
                } else {
                    // Checkbox: Must have at least one selection
                    if (!selection || selection.length === 0) return false;
                }
            }
        }
        return true;
    }, [product?.modifier_groups, modifiers]);

    // ═══════════════════════════════════════════════════════════════════════
    // PRICE CALCULATION: Base + modifiers
    // ═══════════════════════════════════════════════════════════════════════
    const calculatedPrice = useMemo(() => {
        let total = product?.price || 0;

        if (product?.modifier_groups) {
            for (const group of product.modifier_groups) {
                const selection = modifiers[group.id];

                if (selection) {
                    if (Array.isArray(selection)) {
                        // Multi-select
                        for (const optionId of selection) {
                            const option = group.options?.find((o) => o.id === optionId);
                            if (option) total += option.price || 0;
                        }
                    } else {
                        // Single-select
                        const option = group.options?.find((o) => o.id === selection);
                        if (option) total += option.price || 0;
                    }
                }
            }
        }

        return total * quantity;
    }, [product, modifiers, quantity]);

    // ═══════════════════════════════════════════════════════════════════════
    // CART INJECTION
    // ═══════════════════════════════════════════════════════════════════════
    const handleAddToCart = () => {
        if (!isSelectionComplete) return;

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

        // Show toast
        setShowToast(true);
        setTimeout(() => setShowToast(false), 3000);
    };

    const handleGoToCheckout = () => {
        openCart();
        router.visit(`/${tenantData.slug}/checkout`);
    };

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════
    if (!product) {
        return (
            <StoreLayout>
                <Head title="Producto no encontrado" />
                <div className="max-w-7xl mx-auto px-4 py-16 text-center">
                    <p className="text-gray-500">Producto no encontrado.</p>
                    <Link
                        href={`/${tenantData.slug}`}
                        className="mt-4 inline-block text-blue-600 hover:underline"
                    >
                        Volver al catálogo
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    return (
        <StoreLayout>
            <Head title={product.name} />

            {/* Toast Notification */}
            {showToast && (
                <div className="fixed top-20 left-1/2 -translate-x-1/2 z-50 animate-bounce">
                    <div
                        className="flex items-center gap-2 px-4 py-3 rounded-lg shadow-lg text-white"
                        style={{ backgroundColor: primaryColor }}
                    >
                        <CheckCircle className="h-5 w-5" />
                        <span className="font-medium">Producto agregado al carrito</span>
                    </div>
                </div>
            )}

            <div className="max-w-7xl mx-auto px-4 py-6">
                {/* Back Link */}
                <Link
                    href={`/${tenantData.slug}`}
                    className="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6"
                >
                    <ArrowLeft className="h-4 w-4 mr-2" />
                    Volver al catálogo
                </Link>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {/* Product Image */}
                    <div className="aspect-square rounded-2xl overflow-hidden bg-gray-100">
                        {product.image ? (
                            <img
                                src={product.image}
                                alt={product.name}
                                className="w-full h-full object-cover"
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
                            <p
                                className="text-2xl font-bold mt-2"
                                style={{ color: primaryColor }}
                            >
                                {formatCurrency(product.price)}
                            </p>
                        </div>

                        {product.description && (
                            <p className="text-gray-600">{product.description}</p>
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
                            />
                        </div>

                        {/* Quantity & Add to Cart */}
                        <div className="border-t border-gray-200 pt-6 space-y-4">
                            {/* Quantity Selector */}
                            <div className="flex items-center justify-between">
                                <span className="font-semibold text-gray-900">Cantidad</span>
                                <div className="flex items-center gap-4">
                                    <button
                                        type="button"
                                        onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                                        className="p-2 rounded-full border border-gray-300 hover:bg-gray-100 transition-colors"
                                        aria-label="Reducir cantidad"
                                    >
                                        <Minus className="h-4 w-4" />
                                    </button>
                                    <span className="text-xl font-bold w-8 text-center">
                                        {quantity}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setQuantity((q) => q + 1)}
                                        className="p-2 rounded-full border border-gray-300 hover:bg-gray-100 transition-colors"
                                        aria-label="Aumentar cantidad"
                                    >
                                        <Plus className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

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
                                disabled={!isSelectionComplete}
                                className={`w-full flex items-center justify-center gap-2 px-6 py-4 text-white font-bold rounded-xl transition-all
                                    ${!isSelectionComplete
                                        ? 'opacity-50 cursor-not-allowed grayscale'
                                        : 'hover:opacity-90 hover:scale-[1.02]'
                                    }`}
                                style={{ backgroundColor: primaryColor }}
                                aria-label="Agregar al carrito"
                            >
                                <ShoppingCart className="h-5 w-5" />
                                Agregar {formatCurrency(calculatedPrice)}
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
