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

import React, { useState, Fragment, useEffect, useRef } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { ShoppingCart, ShoppingBag, ChevronLeft, X, Percent } from 'lucide-react';
import { cn } from '@/lib/utils';
import useCartStore from '@/Stores/useCartStore';

// Subcomponents
import CouponSection from './Cart/CouponSection';
import CartItem from './Cart/CartItem';
import CheckoutForm from './Cart/CheckoutForm';

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

    // ═══════════════════════════════════════════════════════════════════════
    // ANIMATION: Badge bounce on item count change
    // ═══════════════════════════════════════════════════════════════════════
    const [isBouncing, setIsBouncing] = useState(false);
    const prevTotalRef = useRef(totalItems);

    useEffect(() => {
        if (totalItems > prevTotalRef.current) {
            setIsBouncing(true);
            const timer = setTimeout(() => setIsBouncing(false), 300);
            return () => clearTimeout(timer);
        }
        prevTotalRef.current = totalItems;
    }, [totalItems]);

    // Reset UI state when drawer opens
    useEffect(() => {
        if (isOpen) {
            setStep(STEPS.CART);
            setErrors({});
            clearError();
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
            {/* Floating Button - Animated entrance */}
            <button
                onClick={() => setIsOpen(true)}
                className={cn(
                    "fixed bottom-6 right-6 z-40",
                    "w-16 h-16 rounded-full",
                    "bg-[var(--primary-color,#6366f1)] hover:brightness-90 text-[var(--primary-text-color,#ffffff)]",
                    "shadow-2xl shadow-black/30 hover:shadow-lg",
                    "flex items-center justify-center",
                    "transition-all active:scale-95",
                    "group",
                    // Animation: scale-in from 0 when appearing
                    "animate-[scale-in_0.2s_ease-out_forwards]"
                )}
                style={{
                    animation: 'scale-in 0.2s ease-out forwards',
                }}
            >
                <ShoppingCart className="w-6 h-6 group-hover:scale-110 transition-transform" />
                {totalItems > 0 && (
                    <span className={cn(
                        "absolute -top-1 -right-1 w-6 h-6 bg-rose-500 rounded-full text-xs font-bold flex items-center justify-center shadow-lg",
                        isBouncing && "animate-bounce"
                    )}>
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
                                                            <CartItem
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

                                                        {/* Coupon Section */}
                                                        <CouponSection
                                                            couponCode={couponCode}
                                                            showCouponInput={showCouponInput}
                                                            setShowCouponInput={setShowCouponInput}
                                                            couponInput={couponInput}
                                                            setCouponInput={setCouponInput}
                                                            isApplyingCoupon={isApplyingCoupon}
                                                            applyCoupon={applyCoupon}
                                                            removeCoupon={removeCoupon}
                                                            couponError={couponError}
                                                        />

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
                                            <CheckoutForm
                                                // Form State
                                                customerName={customerName} setCustomerName={setCustomerName}
                                                customerPhone={customerPhone} setCustomerPhone={setCustomerPhone}
                                                deliveryType={deliveryType} setDeliveryType={setDeliveryType}
                                                customerAddress={customerAddress} setCustomerAddress={setCustomerAddress}
                                                customerNotes={customerNotes} setCustomerNotes={setCustomerNotes}

                                                // Order Data
                                                items={items}
                                                grandTotal={grandTotal}

                                                // Submission
                                                handleSubmitOrder={handleSubmitOrder}
                                                isSubmitting={isSubmitting}
                                                submitError={submitError}
                                                errors={errors}
                                            />
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
