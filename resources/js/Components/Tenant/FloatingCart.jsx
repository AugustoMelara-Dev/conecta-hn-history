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

import React, { useState, useEffect, useRef } from 'react';
import { ShoppingCart, ShoppingBag, ChevronLeft, Percent } from 'lucide-react';
import { cn } from '@/lib/utils';
import useCartStore from '@/Stores/useCartStore';
import { EmptyState } from '@/Components/Feedback/UXStates';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { ScrollArea } from '@/Components/ui/scroll-area';
import { Separator } from '@/Components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';

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
    const submitDiagnostic = useCartStore(state => state.submitDiagnostic);
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
            <Button
                type="button"
                onClick={() => setIsOpen(true)}
                data-testid="cart-fab"
                aria-label="Abrir carrito"
                className={cn(
                    'fixed bottom-6 right-6 z-40 h-16 w-16 rounded-full',
                    'bg-[var(--primary-color,#6366f1)] text-[var(--primary-text-color,#ffffff)] hover:brightness-90',
                    'shadow-2xl shadow-black/30 hover:shadow-lg',
                    'transition-transform active:scale-95',
                    'group animate-[scale-in_0.2s_ease-out_forwards]',
                    // Animation: scale-in from 0 when appearing
                    isBouncing && 'scale-105',
                )}
            >
                <ShoppingCart className="h-6 w-6 transition-transform group-hover:scale-110" aria-hidden="true" />
                {totalItems > 0 && (
                    <Badge
                        className={cn(
                            'absolute -right-1 -top-1 flex h-6 min-w-6 items-center justify-center rounded-full shadow-lg',
                            isBouncing && 'animate-bounce',
                        )}
                        aria-live="polite"
                    >
                        {totalItems}
                    </Badge>
                )}
            </Button>

            {/* Cart Drawer */}
            <Sheet
                open={isOpen}
                onOpenChange={(open) => {
                    if (!open && isSubmitting) {
                        return;
                    }

                    setIsOpen(open);
                }}
            >
                <SheetContent
                    side="right"
                    data-testid="cart-drawer"
                    className="flex h-full w-full max-w-md flex-col gap-0 overflow-hidden p-0 sm:max-w-md"
                >
                    <div className="flex h-full min-h-0 flex-col">
                        <span className="sr-only" aria-live="polite">
                            {totalItems} productos en el carrito
                        </span>
                        {(submitError || couponError) ? (
                            <span className="sr-only" aria-live="assertive">
                                {submitError || couponError}
                            </span>
                        ) : null}
                                        {/* ═══════════════════════════════════════════════════════════════
                                            HEADER
                                        ═══════════════════════════════════════════════════════════════ */}
                        <SheetHeader className="border-b px-5 py-4 pr-12 text-left">
                            <div className="flex min-w-0 items-center gap-3">
                                {step === STEPS.CHECKOUT ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleBackToCart}
                                        aria-label="Volver al carrito"
                                        className="-ml-2 shrink-0"
                                    >
                                        <ChevronLeft className="h-4 w-4" data-icon aria-hidden="true" />
                                        Volver
                                    </Button>
                                ) : (
                                    <ShoppingBag className="h-5 w-5 shrink-0 text-primary" data-icon aria-hidden="true" />
                                )}
                                <div className="min-w-0">
                                    <SheetTitle className="truncate">
                                        {step === STEPS.CART ? 'Mi carrito' : 'Datos de entrega'}
                                    </SheetTitle>
                                    <SheetDescription>
                                        {step === STEPS.CART
                                            ? 'Revisa tu pedido antes de continuar.'
                                            : 'Completa los datos para registrar el pedido.'}
                                    </SheetDescription>
                                </div>
                                {step === STEPS.CART ? (
                                    <Badge variant="secondary" aria-live="polite" className="ml-auto shrink-0">
                                        {totalItems}
                                    </Badge>
                                ) : null}
                            </div>
                        </SheetHeader>

                                        {/* ═══════════════════════════════════════════════════════════════
                                            STEP: CART VIEW
                                        ═══════════════════════════════════════════════════════════════ */}
                                        {step === STEPS.CART && (
                                            <>
                                                <ScrollArea className="min-h-0 flex-1">
                                                    <div className="grid gap-4 p-4">
                                                    {items.length === 0 ? (
                                                        <EmptyState
                                                            icon={ShoppingBag}
                                                            eyebrow="Carrito"
                                                            title="Tu carrito está vacío"
                                                            description="Agrega productos del catálogo para continuar con el pedido."
                                                            testId="cart-empty-state"
                                                        />
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
                                                </ScrollArea>

                                                {/* Footer: Coupon + Totals + Continue */}
                                                {items.length > 0 && (
                                                    <div className="grid gap-4 border-t bg-card p-4 text-card-foreground shadow-sm">

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
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={clearCart}
                                                            className="w-full"
                                                        >
                                                            Vaciar carrito
                                                        </Button>

                                                        {/* Totals Breakdown */}
                                                        <div className="grid gap-2">
                                                            {/* Subtotal */}
                                                            <div className="flex items-center justify-between text-sm">
                                                                <span className="text-muted-foreground">Subtotal</span>
                                                                <span className={cn(
                                                                    'text-foreground',
                                                                    discountAmount > 0 && 'line-through'
                                                                )}>
                                                                    L. {subtotal.toFixed(2)}
                                                                </span>
                                                            </div>

                                                            {/* Discount row (if applied) */}
                                                            {discountAmount > 0 && (
                                                                <div className="flex items-center justify-between text-sm">
                                                                    <span className="flex items-center gap-1 text-foreground">
                                                                        <Percent className="h-3 w-3" data-icon aria-hidden="true" />
                                                                        Descuento ({couponCode})
                                                                    </span>
                                                                    <span className="font-medium text-foreground">
                                                                        - L. {discountAmount.toFixed(2)}
                                                                    </span>
                                                                </div>
                                                            )}

                                                            {/* Final Total */}
                                                            <div className="flex items-center justify-between pt-2">
                                                                <span className="text-lg font-medium text-muted-foreground">Total</span>
                                                                <span className="text-2xl font-bold text-foreground">
                                                                    L. {grandTotal.toFixed(2)}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <Separator />

                                                        {/* Continue button */}
                                                        <Button
                                                            type="button"
                                                            onClick={handleProceedToCheckout}
                                                            data-testid="cart-continue"
                                                            className="w-full"
                                                        >
                                                            Continuar
                                                            <ChevronLeft className="h-5 w-5 rotate-180" data-icon aria-hidden="true" />
                                                        </Button>
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
                                                submitDiagnostic={submitDiagnostic}
                                                errors={errors}
                                            />
                                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
