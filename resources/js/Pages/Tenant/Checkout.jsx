/**
 * Checkout.jsx - The Transactional Terminal (HARDENED)
 *
 * ARCHITECTURE:
 * - Global Store Binding (no local state for form fields)
 * - Guard clause pattern for race condition prevention
 * - Empty cart redirect protection
 * - WCAG-compliant form accessibility
 *
 * SECURITY:
 * - Prevents double-submission via guard clause
 * - Validates form before submission
 * - Protects against empty cart submission
 */

import { useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ShoppingCart,
    Loader2,
    MapPin,
    User,
    Phone,
    Mail,
    FileText,
    Truck,
    Store,
    AlertCircle,
    CheckCircle,
    Trash2,
} from 'lucide-react';
import StoreLayout from '@/Layouts/StoreLayout';
import useCartStore from '@/Stores/useCartStore';
import useTenantContext from '@/Hooks/useTenantContext';
import { formatCurrency } from '@/Utils/currency';

/**
 * CartItemRow - Single item in order summary
 * Optimized: Removed unnecessary useMemo for simple calculation
 */
function CartItemRow({ item, primaryColor, onRemove }) {
    // Direct calculation - more efficient than useMemo for simple arithmetic
    const modifiersTotal = (item.selectedModifiers || []).reduce(
        (sum, m) => sum + (m.price || 0),
        0
    );
    const itemTotal = (item.price + modifiersTotal) * item.quantity;

    return (
        <div className="flex items-start gap-4 py-4 border-b border-gray-100 last:border-0">
            {item.image && (
                <img
                    src={item.image}
                    alt={item.name}
                    className="h-16 w-16 rounded-lg object-cover"
                    loading="lazy"
                />
            )}
            <div className="flex-1 min-w-0">
                <h4 className="font-medium text-gray-900">{item.name}</h4>
                <p className="text-sm text-gray-500">Cantidad: {item.quantity}</p>
                {item.selectedModifiers?.length > 0 && (
                    <p className="text-xs text-gray-400 mt-1">
                        {item.selectedModifiers.map((m) => m.name).join(', ')}
                    </p>
                )}
            </div>
            <div className="text-right">
                <p className="font-semibold" style={{ color: primaryColor }}>
                    {formatCurrency(itemTotal)}
                </p>
                <button
                    type="button"
                    onClick={() => onRemove(item.id)}
                    className="text-gray-400 hover:text-red-500 transition-colors mt-1"
                    aria-label={`Eliminar ${item.name}`}
                >
                    <Trash2 className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}

/**
 * Checkout - Main Checkout Page
 */
export default function Checkout() {
    // Use centralized tenant context hook
    const { tenant, primaryColor } = useTenantContext();

    // ═══════════════════════════════════════════════════════════════════════
    // GLOBAL STORE BINDING: No local state - everything from Zustand
    // ═══════════════════════════════════════════════════════════════════════
    const {
        items,
        customerName,
        customerPhone,
        customerEmail,
        customerAddress,
        customerNotes,
        deliveryType,
        setCustomerName,
        setCustomerPhone,
        setCustomerEmail,
        setCustomerAddress,
        setCustomerNotes,
        setDeliveryType,
        removeItem,
        getSubtotal,
        getGrandTotal,
        isSubmitting,
        submitError,
        submitOrder,
        clearError,
    } = useCartStore();

    const subtotal = getSubtotal();
    const grandTotal = getGrandTotal();

    // ═══════════════════════════════════════════════════════════════════════
    // EMPTY CART DEFENSE: Redirect if cart becomes empty
    // ═══════════════════════════════════════════════════════════════════════
    useEffect(() => {
        // Only redirect if we're not in the middle of submission
        // and the cart is genuinely empty
        if (items.length === 0 && !isSubmitting && tenant.slug) {
            // Small delay to prevent flash during hydration
            const timeout = setTimeout(() => {
                router.visit(`/${tenant.slug}`);
            }, 100);

            return () => clearTimeout(timeout);
        }
    }, [items.length, isSubmitting, tenant.slug]);

    // ═══════════════════════════════════════════════════════════════════════
    // FORM VALIDATION
    // ═══════════════════════════════════════════════════════════════════════
    const isFormValid = (() => {
        if (items.length === 0) return false;
        if (!customerName || customerName.trim().length < 2) return false;
        if (!customerPhone || customerPhone.trim().length < 8) return false;
        if (deliveryType === 'delivery' && (!customerAddress || customerAddress.trim().length < 5)) {
            return false;
        }
        return true;
    })();

    // ═══════════════════════════════════════════════════════════════════════
    // ORDER SUBMISSION: With Guard Clause
    // ═══════════════════════════════════════════════════════════════════════
    const handleSubmit = async (e) => {
        e.preventDefault();

        // ═══════════════════════════════════════════════════════════════════
        // GUARD CLAUSE: Prevent race condition
        // If already submitting, abort immediately
        // ═══════════════════════════════════════════════════════════════════
        if (isSubmitting) {
            console.warn('[Checkout] Submission already in progress, ignoring duplicate request');
            return;
        }

        // Additional validation guard
        if (!isFormValid) {
            console.warn('[Checkout] Form validation failed');
            return;
        }

        clearError();

        const result = await submitOrder();

        if (result.success && result.whatsappUrl) {
            // WhatsApp Handshake: Exit to messaging platform
            window.location.href = result.whatsappUrl;
        }
    };

    // ═══════════════════════════════════════════════════════════════════════
    // EMPTY CART STATE (Fallback before redirect)
    // ═══════════════════════════════════════════════════════════════════════
    if (items.length === 0) {
        return (
            <StoreLayout>
                <Head title="Checkout" />
                <div className="max-w-7xl mx-auto px-4 py-16 text-center">
                    <ShoppingCart className="h-20 w-20 mx-auto text-gray-300 mb-4" />
                    <h1 className="text-2xl font-bold text-gray-900 mb-2">
                        Tu carrito está vacío
                    </h1>
                    <p className="text-gray-500 mb-6">
                        Agrega productos para continuar con tu pedido.
                    </p>
                    <Link
                        href={`/${tenant.slug}`}
                        className="inline-flex items-center gap-2 px-6 py-3 rounded-lg text-white font-medium transition-all hover:opacity-90"
                        style={{ backgroundColor: primaryColor }}
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Ver catálogo
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    return (
        <StoreLayout>
            <Head title="Finalizar Pedido" />

            <div className="max-w-7xl mx-auto px-4 py-6">
                {/* Back Link */}
                <Link
                    href={`/${tenant.slug}`}
                    className="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6"
                >
                    <ArrowLeft className="h-4 w-4 mr-2" />
                    Seguir comprando
                </Link>

                <h1 className="text-3xl font-bold text-gray-900 mb-8">
                    Finalizar Pedido
                </h1>

                <form onSubmit={handleSubmit} noValidate>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* ═══════════════════════════════════════════════════
                            LEFT: Customer Form
                        ═══════════════════════════════════════════════════ */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Customer Information */}
                            <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                                <h2 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <User className="h-5 w-5" style={{ color: primaryColor }} />
                                    Información de Contacto
                                </h2>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                                            Nombre *
                                        </label>
                                        <input
                                            type="text"
                                            id="name"
                                            value={customerName}
                                            onChange={(e) => setCustomerName(e.target.value)}
                                            placeholder="Tu nombre"
                                            className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-1"
                                            style={{ '--tw-ring-color': primaryColor }}
                                            required
                                            minLength={2}
                                            aria-required="true"
                                            aria-label="Nombre completo"
                                            autoComplete="name"
                                        />
                                    </div>
                                    <div>
                                        <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1">
                                            Teléfono *
                                        </label>
                                        <div className="relative">
                                            <Phone className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                                            <input
                                                type="tel"
                                                id="phone"
                                                value={customerPhone}
                                                onChange={(e) => setCustomerPhone(e.target.value)}
                                                placeholder="+504 9999-9999"
                                                className="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-1"
                                                style={{ '--tw-ring-color': primaryColor }}
                                                required
                                                minLength={8}
                                                aria-required="true"
                                                aria-label="Número de teléfono"
                                                autoComplete="tel"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                                        Email (opcional)
                                    </label>
                                    <div className="relative">
                                        <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                                        <input
                                            type="email"
                                            id="email"
                                            value={customerEmail}
                                            onChange={(e) => setCustomerEmail(e.target.value)}
                                            placeholder="tu@email.com"
                                            className="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-1"
                                            style={{ '--tw-ring-color': primaryColor }}
                                            aria-label="Correo electrónico"
                                            autoComplete="email"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Delivery Options */}
                            <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                                <h2 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <Truck className="h-5 w-5" style={{ color: primaryColor }} />
                                    Método de Entrega
                                </h2>

                                <div className="grid grid-cols-2 gap-4" role="radiogroup" aria-label="Método de entrega">
                                    <button
                                        type="button"
                                        onClick={() => setDeliveryType('pickup')}
                                        className={`flex items-center justify-center gap-2 p-4 rounded-lg border-2 transition-all
                                            ${deliveryType === 'pickup'
                                                ? 'bg-opacity-10'
                                                : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                        style={{
                                            borderColor: deliveryType === 'pickup' ? primaryColor : undefined,
                                            backgroundColor: deliveryType === 'pickup' ? `${primaryColor}10` : undefined,
                                        }}
                                        role="radio"
                                        aria-checked={deliveryType === 'pickup'}
                                        aria-label="Recoger en tienda"
                                    >
                                        <Store className="h-5 w-5" />
                                        <span className="font-medium">Recoger</span>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setDeliveryType('delivery')}
                                        className={`flex items-center justify-center gap-2 p-4 rounded-lg border-2 transition-all
                                            ${deliveryType === 'delivery'
                                                ? 'bg-opacity-10'
                                                : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                        style={{
                                            borderColor: deliveryType === 'delivery' ? primaryColor : undefined,
                                            backgroundColor: deliveryType === 'delivery' ? `${primaryColor}10` : undefined,
                                        }}
                                        role="radio"
                                        aria-checked={deliveryType === 'delivery'}
                                        aria-label="Delivery a domicilio"
                                    >
                                        <Truck className="h-5 w-5" />
                                        <span className="font-medium">Delivery</span>
                                    </button>
                                </div>

                                {deliveryType === 'delivery' && (
                                    <div className="animate-fade-in">
                                        <label htmlFor="address" className="block text-sm font-medium text-gray-700 mb-1">
                                            Dirección de entrega *
                                        </label>
                                        <div className="relative">
                                            <MapPin className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                                            <textarea
                                                id="address"
                                                value={customerAddress}
                                                onChange={(e) => setCustomerAddress(e.target.value)}
                                                placeholder="Colonia, calle, número de casa, referencias..."
                                                rows={3}
                                                className="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-1"
                                                style={{ '--tw-ring-color': primaryColor }}
                                                required={deliveryType === 'delivery'}
                                                minLength={5}
                                                aria-required={deliveryType === 'delivery'}
                                                aria-label="Dirección de entrega"
                                                autoComplete="street-address"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Notes */}
                            <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
                                <h2 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <FileText className="h-5 w-5" style={{ color: primaryColor }} />
                                    Notas adicionales
                                </h2>
                                <textarea
                                    id="checkout-notes"
                                    value={customerNotes}
                                    onChange={(e) => setCustomerNotes(e.target.value)}
                                    placeholder="Instrucciones especiales para tu pedido..."
                                    rows={3}
                                    className="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-1"
                                    style={{ '--tw-ring-color': primaryColor }}
                                    aria-label="Notas adicionales para el pedido"
                                />
                            </div>
                        </div>

                        {/* ═══════════════════════════════════════════════════
                            RIGHT: Order Summary
                        ═══════════════════════════════════════════════════ */}
                        <div className="lg:col-span-1">
                            <div className="bg-white rounded-xl border border-gray-200 p-6 sticky top-40">
                                <h2 className="text-lg font-semibold text-gray-900 mb-4">
                                    Resumen del Pedido
                                </h2>

                                {/* Cart Items */}
                                <div className="max-h-80 overflow-y-auto" role="list" aria-label="Productos en el carrito">
                                    {items.map((item) => (
                                        <CartItemRow
                                            key={item.id}
                                            item={item}
                                            primaryColor={primaryColor}
                                            onRemove={removeItem}
                                        />
                                    ))}
                                </div>

                                {/* Totals */}
                                <div className="border-t border-gray-200 mt-4 pt-4 space-y-2">
                                    <div className="flex justify-between text-gray-600">
                                        <span>Subtotal</span>
                                        <span>{formatCurrency(subtotal)}</span>
                                    </div>
                                    <div className="flex justify-between text-xl font-bold text-gray-900">
                                        <span>Total</span>
                                        <span style={{ color: primaryColor }}>
                                            {formatCurrency(grandTotal)}
                                        </span>
                                    </div>
                                </div>

                                {/* Error Message */}
                                {submitError && (
                                    <div
                                        role="alert"
                                        className="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2"
                                    >
                                        <AlertCircle className="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5" />
                                        <p className="text-sm text-red-700">{submitError}</p>
                                    </div>
                                )}

                                {/* Submit Button */}
                                <button
                                    type="submit"
                                    disabled={!isFormValid || isSubmitting}
                                    className={`w-full mt-6 flex items-center justify-center gap-2 px-6 py-4 text-white font-bold rounded-xl transition-all
                                        ${(!isFormValid || isSubmitting)
                                            ? 'opacity-50 cursor-not-allowed'
                                            : 'hover:opacity-90'
                                        }`}
                                    style={{ backgroundColor: primaryColor }}
                                    aria-label={isSubmitting ? 'Procesando pedido' : 'Confirmar pedido'}
                                    aria-busy={isSubmitting}
                                >
                                    {isSubmitting ? (
                                        <>
                                            <Loader2 className="h-5 w-5 animate-spin" />
                                            Procesando...
                                        </>
                                    ) : (
                                        <>
                                            <CheckCircle className="h-5 w-5" />
                                            Confirmar Pedido
                                        </>
                                    )}
                                </button>

                                <p className="mt-4 text-xs text-gray-500 text-center">
                                    Al confirmar, serás redirigido a WhatsApp para finalizar tu pedido.
                                </p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </StoreLayout>
    );
}
