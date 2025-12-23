/**
 * FloatingCart - Multi-Step Checkout Wizard for Pro Tier
 * 
 * TRANSACTIONAL HANDSHAKE PATTERN:
 * The cart NEVER opens WhatsApp directly. Flow:
 * 1. Cart View - Review items
 * 2. Checkout Form - Capture customer data
 * 3. Submit to API - Persist order in database
 * 4. Redirect to WhatsApp - With order ID in message
 * 
 * @usage Conditionally rendered: {tenantPlan !== 'semilla' && <FloatingCart />}
 */

import { useState, Fragment, useEffect } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import {
    ShoppingCart, X, Trash2, Minus, Plus, MessageCircle,
    ShoppingBag, ChevronLeft, User, MapPin, Loader2,
    Package, Truck, Check, AlertCircle, Tag, Percent
} from 'lucide-react';
import { cn } from '@/lib/utils';
import useCartStore from '@/Stores/useCartStore';

// ═══════════════════════════════════════════════════════════════════════
// WIZARD STEPS
// ═══════════════════════════════════════════════════════════════════════
const STEPS = {
    CART: 'cart',
    CHECKOUT: 'checkout',
    CONFIRMING: 'confirming',
};

export default function FloatingCart() {
    const [isOpen, setIsOpen] = useState(false);
    const [step, setStep] = useState(STEPS.CART);
    const [errors, setErrors] = useState({});
    const [couponInput, setCouponInput] = useState('');

    // Progressive Disclosure: Show coupon input only when user wants it
    // Initializes to true if a coupon is already applied (for consistency)
    const [showCouponInput, setShowCouponInput] = useState(false);

    // Store state
    const items = useCartStore(state => state.items);
    const tenantName = useCartStore(state => state.tenantName);
    const removeItem = useCartStore(state => state.removeItem);
    const updateQuantity = useCartStore(state => state.updateQuantity);
    const clearCart = useCartStore(state => state.clearCart);
    const getTotalItems = useCartStore(state => state.getTotalItems);
    const getGrandTotal = useCartStore(state => state.getGrandTotal);

    // Customer data
    const customerName = useCartStore(state => state.customerName);
    const customerPhone = useCartStore(state => state.customerPhone);
    const customerAddress = useCartStore(state => state.customerAddress);
    const customerNotes = useCartStore(state => state.customerNotes);
    const deliveryType = useCartStore(state => state.deliveryType);

    // Customer data setters
    const setCustomerName = useCartStore(state => state.setCustomerName);
    const setCustomerPhone = useCartStore(state => state.setCustomerPhone);
    const setCustomerAddress = useCartStore(state => state.setCustomerAddress);
    const setCustomerNotes = useCartStore(state => state.setCustomerNotes);
    const setDeliveryType = useCartStore(state => state.setDeliveryType);

    // Submission state
    const isSubmitting = useCartStore(state => state.isSubmitting);
    const submitError = useCartStore(state => state.submitError);
    const submitOrder = useCartStore(state => state.submitOrder);
    const validateCheckout = useCartStore(state => state.validateCheckout);
    const clearError = useCartStore(state => state.clearError);

    // Coupon state
    const getSubtotal = useCartStore(state => state.getSubtotal);
    const couponCode = useCartStore(state => state.couponCode);
    const discountAmount = useCartStore(state => state.discountAmount);
    const couponError = useCartStore(state => state.couponError);
    const isApplyingCoupon = useCartStore(state => state.isApplyingCoupon);
    const applyCoupon = useCartStore(state => state.applyCoupon);
    const removeCoupon = useCartStore(state => state.removeCoupon);

    const totalItems = getTotalItems();
    const subtotal = getSubtotal();
    const grandTotal = getGrandTotal();

    // Reset UI state when drawer opens
    useEffect(() => {
        if (isOpen) {
            setStep(STEPS.CART);
            setErrors({});
            clearError();

            // Progressive Disclosure: Sync showCouponInput with global state
            // If coupon is already applied, keep it visible
            // If not, hide the input for clean UX (user must click trigger to reveal)
            setShowCouponInput(!!couponCode);
            setCouponInput('');
        }
    }, [isOpen, clearError, couponCode]);

    // ═══════════════════════════════════════════════════════════════════════
    // HANDLERS
    // ═══════════════════════════════════════════════════════════════════════

    const handleProceedToCheckout = () => {
        setStep(STEPS.CHECKOUT);
        setErrors({});
    };

    const handleBackToCart = () => {
        setStep(STEPS.CART);
        setErrors({});
    };

    const handleSubmitOrder = async () => {
        // Validate form
        const validation = validateCheckout();
        if (!validation.isValid) {
            setErrors(validation.errors);
            return;
        }

        setErrors({});
        const result = await submitOrder();

        if (result.success) {
            // Redirect to WhatsApp with order ID
            if (result.whatsappUrl) {
                window.open(result.whatsappUrl, '_blank');
            }
            // Close cart after successful submission
            setIsOpen(false);
            setStep(STEPS.CART);
        } else {
            // Show error
            if (result.validationErrors) {
                setErrors(result.validationErrors);
            }
        }
    };

    // Don't render button if cart is empty
    if (totalItems === 0 && !isOpen) {
        return null;
    }

    return (
        <>
            {/* Floating Button */}
            <button
                onClick={() => setIsOpen(true)}
                className={cn(
                    "fixed bottom-6 right-6 z-40",
                    "w-16 h-16 rounded-full",
                    "bg-[var(--primary-color,#6366f1)] hover:brightness-90 text-[var(--primary-text-color,#ffffff)]",
                    "shadow-2xl shadow-black/30 hover:shadow-lg",
                    "flex items-center justify-center",
                    "transition-all active:scale-95",
                    "group"
                )}
            >
                <ShoppingCart className="w-6 h-6 group-hover:scale-110 transition-transform" />
                {totalItems > 0 && (
                    <span className="absolute -top-1 -right-1 w-6 h-6 bg-rose-500 rounded-full text-xs font-bold flex items-center justify-center shadow-lg">
                        {totalItems}
                    </span>
                )}
            </button>

            {/* Cart Drawer */}
            <Transition appear show={isOpen} as={Fragment}>
                <Dialog as="div" className="relative z-50" onClose={() => !isSubmitting && setIsOpen(false)}>
                    {/* Backdrop */}
                    <Transition.Child
                        as={Fragment}
                        enter="ease-out duration-300"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="ease-in duration-200"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" />
                    </Transition.Child>

                    {/* Drawer Container */}
                    <div className="fixed inset-0 overflow-hidden">
                        <div className="absolute inset-0 overflow-hidden">
                            <div className="fixed inset-y-0 right-0 flex max-w-full pl-10">
                                <Transition.Child
                                    as={Fragment}
                                    enter="transform transition ease-in-out duration-300"
                                    enterFrom="translate-x-full"
                                    enterTo="translate-x-0"
                                    leave="transform transition ease-in-out duration-200"
                                    leaveFrom="translate-x-0"
                                    leaveTo="translate-x-full"
                                >
                                    <Dialog.Panel className="w-screen max-w-md transform overflow-hidden bg-slate-900 shadow-2xl flex flex-col h-full">
                                        {/* ═══════════════════════════════════════════════════════════════
                                            HEADER
                                        ═══════════════════════════════════════════════════════════════ */}
                                        <div className="flex items-center justify-between p-4 border-b border-white/10">
                                            {step === STEPS.CHECKOUT ? (
                                                <button
                                                    onClick={handleBackToCart}
                                                    className="flex items-center gap-2 text-slate-400 hover:text-white transition-colors"
                                                >
                                                    <ChevronLeft className="w-5 h-5" />
                                                    <span>Volver</span>
                                                </button>
                                            ) : (
                                                <Dialog.Title className="text-lg font-bold text-white flex items-center gap-2">
                                                    <ShoppingBag className="w-5 h-5" style={{ color: 'var(--primary-color, #818cf8)' }} />
                                                    {step === STEPS.CART ? `Mi Carrito (${totalItems})` : 'Datos de Entrega'}
                                                </Dialog.Title>
                                            )}

                                            <button
                                                onClick={() => !isSubmitting && setIsOpen(false)}
                                                disabled={isSubmitting}
                                                className="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center text-slate-400 hover:text-white transition-colors disabled:opacity-50"
                                            >
                                                <X className="w-5 h-5" />
                                            </button>
                                        </div>

                                        {/* ═══════════════════════════════════════════════════════════════
                                            STEP: CART VIEW
                                        ═══════════════════════════════════════════════════════════════ */}
                                        {step === STEPS.CART && (
                                            <>
                                                <div className="flex-1 overflow-y-auto p-4 space-y-4">
                                                    {items.length === 0 ? (
                                                        <div className="text-center py-12">
                                                            <ShoppingBag className="w-16 h-16 text-slate-700 mx-auto mb-4" />
                                                            <p className="text-slate-400">Tu carrito está vacío</p>
                                                        </div>
                                                    ) : (
                                                        items.map(item => (
                                                            <CartItemCard
                                                                key={item.id}
                                                                item={item}
                                                                onRemove={() => removeItem(item.id)}
                                                                onUpdateQuantity={(qty) => updateQuantity(item.id, qty)}
                                                            />
                                                        ))
                                                    )}
                                                </div>

                                                {/* Footer: Coupon + Totals + Continue */}
                                                {items.length > 0 && (
                                                    <div className="p-4 border-t border-white/10 bg-slate-900/80 backdrop-blur-xl space-y-4">

                                                        {/* Coupon Section - Progressive Disclosure */}
                                                        <div className="space-y-2">
                                                            {couponCode ? (
                                                                // ✅ Applied coupon display
                                                                <div className="flex items-center justify-between bg-emerald-500/10 border border-emerald-500/30 rounded-lg px-3 py-2">
                                                                    <div className="flex items-center gap-2">
                                                                        <Tag className="w-4 h-4 text-emerald-400" />
                                                                        <span className="text-sm font-medium text-emerald-300">{couponCode}</span>
                                                                    </div>
                                                                    <button
                                                                        onClick={() => {
                                                                            removeCoupon();
                                                                            // Show input again for retry
                                                                            setShowCouponInput(true);
                                                                        }}
                                                                        className="text-slate-400 hover:text-rose-400 transition-colors"
                                                                        title="Eliminar cupón"
                                                                    >
                                                                        <X className="w-4 h-4" />
                                                                    </button>
                                                                </div>
                                                            ) : showCouponInput ? (
                                                                // 📝 Coupon input form (revealed)
                                                                <div className="space-y-2">
                                                                    <div className="flex gap-2">
                                                                        <input
                                                                            type="text"
                                                                            value={couponInput}
                                                                            onChange={(e) => setCouponInput(e.target.value.toUpperCase())}
                                                                            placeholder="Ingresa tu código"
                                                                            autoFocus
                                                                            className="flex-1 px-3 py-2 bg-slate-800 border border-white/10 rounded-lg text-white text-sm placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                                        />
                                                                        <button
                                                                            onClick={async () => {
                                                                                const result = await applyCoupon(couponInput);
                                                                                if (result.success) {
                                                                                    setCouponInput('');
                                                                                    setShowCouponInput(false);
                                                                                }
                                                                            }}
                                                                            disabled={isApplyingCoupon || !couponInput}
                                                                            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-1"
                                                                        >
                                                                            {isApplyingCoupon ? (
                                                                                <Loader2 className="w-4 h-4 animate-spin" />
                                                                            ) : (
                                                                                'Aplicar'
                                                                            )}
                                                                        </button>
                                                                    </div>

                                                                    {/* Cancel button to hide input */}
                                                                    <button
                                                                        onClick={() => {
                                                                            setShowCouponInput(false);
                                                                            setCouponInput('');
                                                                            // Clear any previous error when closing
                                                                        }}
                                                                        className="text-xs text-slate-500 hover:text-slate-400 transition-colors"
                                                                    >
                                                                        Cancelar
                                                                    </button>
                                                                </div>
                                                            ) : (
                                                                // 🎯 Trigger link (hidden state - Progressive Disclosure)
                                                                <button
                                                                    onClick={() => setShowCouponInput(true)}
                                                                    className="flex items-center gap-1.5 text-sm text-indigo-400 hover:text-indigo-300 hover:underline transition-colors"
                                                                >
                                                                    <Tag className="w-3.5 h-3.5" />
                                                                    ¿Tienes un código de descuento?
                                                                </button>
                                                            )}

                                                            {/* Coupon error */}
                                                            {couponError && showCouponInput && (
                                                                <p className="text-xs text-rose-400">{couponError}</p>
                                                            )}
                                                        </div>

                                                        {/* Clear cart link */}
                                                        <button
                                                            onClick={clearCart}
                                                            className="w-full text-sm text-slate-400 hover:text-rose-400 transition-colors"
                                                        >
                                                            Vaciar carrito
                                                        </button>

                                                        {/* Totals Breakdown */}
                                                        <div className="space-y-2">
                                                            {/* Subtotal */}
                                                            <div className="flex items-center justify-between text-sm">
                                                                <span className="text-slate-400">Subtotal</span>
                                                                <span className={cn(
                                                                    "text-slate-300",
                                                                    discountAmount > 0 && "line-through"
                                                                )}>
                                                                    L. {subtotal.toFixed(2)}
                                                                </span>
                                                            </div>

                                                            {/* Discount row (if applied) */}
                                                            {discountAmount > 0 && (
                                                                <div className="flex items-center justify-between text-sm">
                                                                    <span className="text-emerald-400 flex items-center gap-1">
                                                                        <Percent className="w-3 h-3" />
                                                                        Descuento ({couponCode})
                                                                    </span>
                                                                    <span className="text-emerald-400 font-medium">
                                                                        - L. {discountAmount.toFixed(2)}
                                                                    </span>
                                                                </div>
                                                            )}

                                                            {/* Final Total */}
                                                            <div className="flex items-center justify-between pt-2 border-t border-white/10">
                                                                <span className="text-lg font-medium text-slate-300">Total</span>
                                                                <span className="text-2xl font-bold text-white">
                                                                    L. {grandTotal.toFixed(2)}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        {/* Continue button */}
                                                        <button
                                                            onClick={handleProceedToCheckout}
                                                            className={cn(
                                                                "w-full py-4 rounded-xl font-bold flex items-center justify-center gap-2",
                                                                "bg-[var(--primary-color,#6366f1)] hover:brightness-90 text-white",
                                                                "shadow-lg transition-all active:scale-98"
                                                            )}
                                                        >
                                                            Continuar
                                                            <ChevronLeft className="w-5 h-5 rotate-180" />
                                                        </button>
                                                    </div>
                                                )}
                                            </>
                                        )}

                                        {/* ═══════════════════════════════════════════════════════════════
                                            STEP: CHECKOUT FORM
                                        ═══════════════════════════════════════════════════════════════ */}
                                        {step === STEPS.CHECKOUT && (
                                            <>
                                                <div className="flex-1 overflow-y-auto p-4 space-y-6">
                                                    {/* Error Banner */}
                                                    {submitError && (
                                                        <div className="bg-rose-500/10 border border-rose-500/30 rounded-xl p-4 flex items-start gap-3">
                                                            <AlertCircle className="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5" />
                                                            <p className="text-sm text-rose-300">{submitError}</p>
                                                        </div>
                                                    )}

                                                    {/* Name Input */}
                                                    <div>
                                                        <label className="block text-sm font-medium text-slate-300 mb-2">
                                                            <User className="w-4 h-4 inline mr-2" />
                                                            Tu nombre *
                                                        </label>
                                                        <input
                                                            type="text"
                                                            value={customerName}
                                                            onChange={(e) => setCustomerName(e.target.value)}
                                                            placeholder="Ej: María García"
                                                            className={cn(
                                                                "w-full px-4 py-3 bg-slate-800 border rounded-xl text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all",
                                                                errors.customerName ? "border-rose-500" : "border-white/10"
                                                            )}
                                                        />
                                                        {errors.customerName && (
                                                            <p className="mt-1 text-sm text-rose-400">{errors.customerName}</p>
                                                        )}
                                                    </div>

                                                    {/* Phone Input */}
                                                    <div>
                                                        <label className="block text-sm font-medium text-slate-300 mb-2">
                                                            📱 Tu teléfono *
                                                        </label>
                                                        <input
                                                            type="tel"
                                                            value={customerPhone}
                                                            onChange={(e) => setCustomerPhone(e.target.value)}
                                                            placeholder="Ej: 9999-1234"
                                                            className={cn(
                                                                "w-full px-4 py-3 bg-slate-800 border rounded-xl text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all",
                                                                errors.customerPhone ? "border-rose-500" : "border-white/10"
                                                            )}
                                                        />
                                                        {errors.customerPhone && (
                                                            <p className="mt-1 text-sm text-rose-400">{errors.customerPhone}</p>
                                                        )}
                                                    </div>

                                                    {/* Delivery Type Selector */}
                                                    <div>
                                                        <label className="block text-sm font-medium text-slate-300 mb-3">
                                                            ¿Cómo recibirás tu pedido?
                                                        </label>
                                                        <div className="grid grid-cols-2 gap-3">
                                                            <button
                                                                type="button"
                                                                onClick={() => setDeliveryType('pickup')}
                                                                className={cn(
                                                                    "p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-all",
                                                                    deliveryType === 'pickup'
                                                                        ? "border-indigo-500 bg-indigo-500/10"
                                                                        : "border-white/10 bg-slate-800/50 hover:border-white/20"
                                                                )}
                                                            >
                                                                <Package className={cn(
                                                                    "w-6 h-6",
                                                                    deliveryType === 'pickup' ? "text-indigo-400" : "text-slate-400"
                                                                )} />
                                                                <span className={cn(
                                                                    "text-sm font-medium",
                                                                    deliveryType === 'pickup' ? "text-white" : "text-slate-300"
                                                                )}>
                                                                    Recoger en tienda
                                                                </span>
                                                                {deliveryType === 'pickup' && (
                                                                    <Check className="w-4 h-4 text-indigo-400" />
                                                                )}
                                                            </button>

                                                            <button
                                                                type="button"
                                                                onClick={() => setDeliveryType('delivery')}
                                                                className={cn(
                                                                    "p-4 rounded-xl border-2 flex flex-col items-center gap-2 transition-all",
                                                                    deliveryType === 'delivery'
                                                                        ? "border-indigo-500 bg-indigo-500/10"
                                                                        : "border-white/10 bg-slate-800/50 hover:border-white/20"
                                                                )}
                                                            >
                                                                <Truck className={cn(
                                                                    "w-6 h-6",
                                                                    deliveryType === 'delivery' ? "text-indigo-400" : "text-slate-400"
                                                                )} />
                                                                <span className={cn(
                                                                    "text-sm font-medium",
                                                                    deliveryType === 'delivery' ? "text-white" : "text-slate-300"
                                                                )}>
                                                                    A domicilio
                                                                </span>
                                                                {deliveryType === 'delivery' && (
                                                                    <Check className="w-4 h-4 text-indigo-400" />
                                                                )}
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {/* Address Input (conditional) */}
                                                    {deliveryType === 'delivery' && (
                                                        <div>
                                                            <label className="block text-sm font-medium text-slate-300 mb-2">
                                                                <MapPin className="w-4 h-4 inline mr-2" />
                                                                Dirección de entrega *
                                                            </label>
                                                            <textarea
                                                                value={customerAddress}
                                                                onChange={(e) => setCustomerAddress(e.target.value)}
                                                                placeholder="Colonia, calle, número de casa, referencias..."
                                                                rows={3}
                                                                className={cn(
                                                                    "w-full px-4 py-3 bg-slate-800 border rounded-xl text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all resize-none",
                                                                    errors.customerAddress ? "border-rose-500" : "border-white/10"
                                                                )}
                                                            />
                                                            {errors.customerAddress && (
                                                                <p className="mt-1 text-sm text-rose-400">{errors.customerAddress}</p>
                                                            )}
                                                        </div>
                                                    )}

                                                    {/* Notes (optional) */}
                                                    <div>
                                                        <label className="block text-sm font-medium text-slate-300 mb-2">
                                                            📝 Notas adicionales (opcional)
                                                        </label>
                                                        <textarea
                                                            value={customerNotes}
                                                            onChange={(e) => setCustomerNotes(e.target.value)}
                                                            placeholder="Instrucciones especiales, alérgenos, etc."
                                                            rows={2}
                                                            className="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all resize-none"
                                                        />
                                                    </div>

                                                    {/* Order Summary */}
                                                    <div className="bg-slate-800/50 rounded-xl p-4 border border-white/5">
                                                        <h4 className="text-sm font-medium text-slate-400 mb-3">Resumen del pedido</h4>
                                                        <div className="space-y-2 text-sm">
                                                            {items.map(item => (
                                                                <div key={item.id} className="flex justify-between text-slate-300">
                                                                    <span>{item.quantity}x {item.name}</span>
                                                                    <span>L. {((item.price + (item.selectedModifiers?.reduce((s, m) => s + m.price, 0) || 0)) * item.quantity).toFixed(2)}</span>
                                                                </div>
                                                            ))}
                                                            <div className="pt-2 border-t border-white/10 flex justify-between font-bold text-white">
                                                                <span>Total</span>
                                                                <span>L. {grandTotal.toFixed(2)}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Footer: Submit Button */}
                                                <div className="p-4 border-t border-white/10 bg-slate-900/80 backdrop-blur-xl">
                                                    <button
                                                        onClick={handleSubmitOrder}
                                                        disabled={isSubmitting}
                                                        className={cn(
                                                            "w-full py-4 rounded-xl font-bold flex items-center justify-center gap-2",
                                                            "bg-emerald-600 hover:bg-emerald-500 text-white",
                                                            "shadow-lg shadow-emerald-900/30 hover:shadow-emerald-500/30",
                                                            "transition-all active:scale-98",
                                                            "disabled:opacity-50 disabled:cursor-not-allowed"
                                                        )}
                                                    >
                                                        {isSubmitting ? (
                                                            <>
                                                                <Loader2 className="w-5 h-5 animate-spin" />
                                                                Procesando...
                                                            </>
                                                        ) : (
                                                            <>
                                                                <MessageCircle className="w-5 h-5" />
                                                                Confirmar Pedido en WhatsApp
                                                            </>
                                                        )}
                                                    </button>

                                                    <p className="text-center text-xs text-slate-500 mt-3">
                                                        Tu pedido será registrado y se abrirá WhatsApp para confirmar
                                                    </p>
                                                </div>
                                            </>
                                        )}
                                    </Dialog.Panel>
                                </Transition.Child>
                            </div>
                        </div>
                    </div>
                </Dialog>
            </Transition>
        </>
    );
}

// ═══════════════════════════════════════════════════════════════════════
// CART ITEM CARD COMPONENT
// ═══════════════════════════════════════════════════════════════════════
function CartItemCard({ item, onRemove, onUpdateQuantity }) {
    const itemTotal = (item.price + (item.selectedModifiers?.reduce((s, m) => s + m.price, 0) || 0)) * item.quantity;

    return (
        <div className="flex gap-3 p-3 bg-slate-800/50 rounded-xl border border-white/5">
            {/* Image */}
            <div className="w-16 h-16 bg-slate-700 rounded-lg overflow-hidden flex-shrink-0">
                {item.image ? (
                    <img
                        src={item.image}
                        alt={item.name}
                        className="w-full h-full object-cover"
                    />
                ) : (
                    <div className="w-full h-full flex items-center justify-center">
                        <ShoppingBag className="w-6 h-6 text-slate-500" />
                    </div>
                )}
            </div>

            {/* Details */}
            <div className="flex-1 min-w-0">
                <h4 className="font-semibold text-white truncate">{item.name}</h4>

                {/* Modifiers */}
                {item.selectedModifiers?.length > 0 && (
                    <div className="mt-1 space-y-0.5">
                        {item.selectedModifiers.map((mod, idx) => (
                            <p key={idx} className="text-xs text-slate-400">
                                + {mod.name}
                                {mod.price > 0 && (
                                    <span className="text-emerald-400 ml-1">
                                        (+L.{mod.price.toFixed(2)})
                                    </span>
                                )}
                            </p>
                        ))}
                    </div>
                )}

                {/* Notes */}
                {item.notes && (
                    <p className="text-xs text-slate-500 mt-1 truncate">
                        📝 {item.notes}
                    </p>
                )}

                {/* Price + Quantity */}
                <div className="flex items-center justify-between mt-2">
                    <span
                        className="font-bold text-sm"
                        style={{ color: 'var(--primary-color, #818cf8)' }}
                    >
                        L. {itemTotal.toFixed(2)}
                    </span>

                    {/* Quantity Controls */}
                    <div className="flex items-center gap-1 bg-slate-700/50 rounded-lg">
                        <button
                            onClick={() => onUpdateQuantity(item.quantity - 1)}
                            className="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-white"
                        >
                            <Minus className="w-3 h-3" />
                        </button>
                        <span className="w-5 text-center text-sm font-medium text-white">
                            {item.quantity}
                        </span>
                        <button
                            onClick={() => onUpdateQuantity(item.quantity + 1)}
                            className="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-white"
                        >
                            <Plus className="w-3 h-3" />
                        </button>
                    </div>
                </div>
            </div>

            {/* Remove Button */}
            <button
                onClick={onRemove}
                className="w-8 h-8 flex-shrink-0 flex items-center justify-center text-rose-400 hover:text-rose-300 transition-colors"
            >
                <Trash2 className="w-4 h-4" />
            </button>
        </div>
    );
}
