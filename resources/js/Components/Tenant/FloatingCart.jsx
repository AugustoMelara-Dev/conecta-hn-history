import React, { useEffect, useRef, useState } from 'react';
import { ChevronLeft, Percent, ShoppingBag, ShoppingCart } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { cn } from '@/lib/utils';
import useCartStore from '@/Stores/useCartStore';

import CouponSection from './Cart/CouponSection';
import CartItem from './Cart/CartItem';
import CheckoutForm from './Cart/CheckoutForm';

const STEPS = {
    CART: 'cart',
    CHECKOUT: 'checkout',
};

export default function FloatingCart() {
    const [isOpen, setIsOpen] = useState(false);
    const [step, setStep] = useState(STEPS.CART);
    const [errors, setErrors] = useState({});
    const [couponInput, setCouponInput] = useState('');
    const [showCouponInput, setShowCouponInput] = useState(false);
    const [isBouncing, setIsBouncing] = useState(false);
    const [tenantTheme, setTenantTheme] = useState({});

    const items = useCartStore(state => state.items);
    const removeItem = useCartStore(state => state.removeItem);
    const updateQuantity = useCartStore(state => state.updateQuantity);
    const clearCart = useCartStore(state => state.clearCart);
    const getTotalItems = useCartStore(state => state.getTotalItems);
    const getGrandTotal = useCartStore(state => state.getGrandTotal);

    const customerName = useCartStore(state => state.customerName);
    const customerPhone = useCartStore(state => state.customerPhone);
    const customerAddress = useCartStore(state => state.customerAddress);
    const customerNotes = useCartStore(state => state.customerNotes);
    const deliveryType = useCartStore(state => state.deliveryType);

    const setCustomerName = useCartStore(state => state.setCustomerName);
    const setCustomerPhone = useCartStore(state => state.setCustomerPhone);
    const setCustomerAddress = useCartStore(state => state.setCustomerAddress);
    const setCustomerNotes = useCartStore(state => state.setCustomerNotes);
    const setDeliveryType = useCartStore(state => state.setDeliveryType);

    const isSubmitting = useCartStore(state => state.isSubmitting);
    const submitError = useCartStore(state => state.submitError);
    const submitOrder = useCartStore(state => state.submitOrder);
    const validateCheckout = useCartStore(state => state.validateCheckout);
    const clearError = useCartStore(state => state.clearError);

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
    const prevTotalRef = useRef(totalItems);
    const themeRef = useRef(null);

    const syncTenantTheme = () => {
        if (!themeRef.current || typeof window === 'undefined') {
            return;
        }

        const themeSource = themeRef.current.closest('[style*="--tenant-primary"]')
            || document.querySelector('[style*="--tenant-primary"]')
            || themeRef.current;
        const computed = window.getComputedStyle(themeSource);
        const primary = computed.getPropertyValue('--tenant-primary').trim();
        const foreground = computed.getPropertyValue('--tenant-primary-foreground').trim();

        if (primary) {
            setTenantTheme({
                '--tenant-primary': primary,
                '--tenant-primary-foreground': foreground || '#ffffff',
            });
        }
    };

    useEffect(() => {
        if (totalItems > prevTotalRef.current) {
            setIsBouncing(true);
            const timer = setTimeout(() => setIsBouncing(false), 300);
            prevTotalRef.current = totalItems;
            return () => clearTimeout(timer);
        }

        prevTotalRef.current = totalItems;
    }, [totalItems]);

    useEffect(() => {
        if (isOpen) {
            syncTenantTheme();
            setStep(STEPS.CART);
            setErrors({});
            clearError();
            setShowCouponInput(!!couponCode);
            setCouponInput('');
        }
    }, [isOpen, clearError, couponCode]);

    const handleOpenChange = (open) => {
        if (!isSubmitting) {
            if (open) {
                syncTenantTheme();
            }
            setIsOpen(open);
        }
    };

    const handleProceedToCheckout = () => {
        setStep(STEPS.CHECKOUT);
        setErrors({});
    };

    const handleBackToCart = () => {
        setStep(STEPS.CART);
        setErrors({});
    };

    const handleSubmitOrder = async () => {
        const validation = validateCheckout();
        if (!validation.isValid) {
            setErrors(validation.errors);
            return;
        }

        setErrors({});
        const result = await submitOrder();

        if (result.success) {
            if (result.whatsappUrl) {
                window.open(result.whatsappUrl, '_blank');
            }

            setIsOpen(false);
            setStep(STEPS.CART);
        } else if (result.validationErrors) {
            setErrors(result.validationErrors);
        }
    };

    if (totalItems === 0 && !isOpen) {
        return null;
    }

    return (
        <div ref={themeRef}>
            <Sheet open={isOpen} onOpenChange={handleOpenChange}>
            <Button
                type="button"
                onClick={() => {
                    syncTenantTheme();
                    setIsOpen(true);
                }}
                data-testid="floating-cart-open"
                aria-label={`Abrir carrito (${totalItems} productos)`}
                className={cn(
                    'fixed bottom-24 right-5 z-40 size-16 rounded-full !bg-[var(--tenant-primary)] !text-[var(--tenant-primary-foreground)] shadow-lg hover:brightness-95 active:scale-95 md:bottom-6 md:right-6',
                    isBouncing && 'animate-bounce'
                )}
            >
                <ShoppingCart data-icon="inline-start" aria-hidden="true" />
                {totalItems > 0 && (
                    <Badge
                        className="absolute -right-1 -top-1 size-6 justify-center rounded-full border-background bg-destructive p-0 text-xs"
                        aria-label={`${totalItems} productos en el carrito`}
                    >
                        {totalItems}
                    </Badge>
                )}
            </Button>

            <SheetContent
                side="right"
                data-testid="floating-cart-sheet"
                style={tenantTheme}
                className="flex h-full w-full flex-col gap-0 p-0 sm:max-w-md"
            >
                <SheetHeader className="border-b px-4 py-4 pr-12 text-left">
                    <div className="flex items-center gap-3">
                        {step === STEPS.CHECKOUT && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={handleBackToCart}
                                data-testid="checkout-back-to-cart"
                                aria-label="Volver al carrito"
                            >
                                <ChevronLeft data-icon="inline-start" aria-hidden="true" />
                            </Button>
                        )}
                        <div className="min-w-0">
                            <SheetTitle className="flex items-center gap-2">
                                {step === STEPS.CART ? (
                                    <>
                                        <ShoppingBag className="size-5 text-primary" aria-hidden="true" />
                                        Mi carrito
                                        <Badge variant="secondary">{totalItems}</Badge>
                                    </>
                                ) : (
                                    'Datos de entrega'
                                )}
                            </SheetTitle>
                            <SheetDescription>
                                {step === STEPS.CART
                                    ? 'Revisa tus productos antes de continuar.'
                                    : 'Completa tus datos para registrar el pedido.'}
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                {step === STEPS.CART && (
                    <>
                        <div className="flex-1 overflow-y-auto p-4">
                            {items.length === 0 ? (
                                <Card className="border-dashed">
                                    <CardContent className="flex flex-col items-center gap-3 px-6 py-12 text-center">
                                        <ShoppingBag className="size-12 text-muted-foreground" aria-hidden="true" />
                                        <p className="text-sm font-medium">Tu carrito está vacío</p>
                                        <p className="text-sm text-muted-foreground">
                                            Agrega un producto del micrositio para empezar tu pedido.
                                        </p>
                                    </CardContent>
                                </Card>
                            ) : (
                                <div className="flex flex-col gap-3">
                                    {items.map(item => (
                                        <CartItem
                                            key={item.id}
                                            item={item}
                                            onRemove={() => removeItem(item.id)}
                                            onUpdateQuantity={(qty) => updateQuantity(item.id, qty)}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>

                        {items.length > 0 && (
                            <div className="border-t bg-background/95 p-4">
                                <div className="flex flex-col gap-4">
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

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={clearCart}
                                        data-testid="cart-clear"
                                        className="self-center text-muted-foreground"
                                    >
                                        Vaciar carrito
                                    </Button>

                                    <div className="flex flex-col gap-2" aria-live="polite">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">Subtotal</span>
                                            <span className={cn('font-medium', discountAmount > 0 && 'text-muted-foreground line-through')}>
                                                L. {subtotal.toFixed(2)}
                                            </span>
                                        </div>

                                        {discountAmount > 0 && (
                                            <div className="flex items-center justify-between text-sm text-primary">
                                                <span className="flex items-center gap-1">
                                                    <Percent className="size-3.5" aria-hidden="true" />
                                                    Descuento ({couponCode})
                                                </span>
                                                <span className="font-medium">- L. {discountAmount.toFixed(2)}</span>
                                            </div>
                                        )}

                                        <Separator />

                                        <div className="flex items-center justify-between">
                                            <span className="text-sm font-medium">Total</span>
                                            <span className="text-2xl font-bold">L. {grandTotal.toFixed(2)}</span>
                                        </div>
                                    </div>

                                    <Button
                                        type="button"
                                        onClick={handleProceedToCheckout}
                                        data-testid="cart-continue-checkout"
                                        className="h-12 !bg-[var(--tenant-primary)] !text-[var(--tenant-primary-foreground)] hover:brightness-95"
                                    >
                                        Continuar
                                        <ChevronLeft data-icon="inline-end" className="rotate-180" aria-hidden="true" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </>
                )}

                {step === STEPS.CHECKOUT && (
                    <CheckoutForm
                        customerName={customerName}
                        setCustomerName={setCustomerName}
                        customerPhone={customerPhone}
                        setCustomerPhone={setCustomerPhone}
                        deliveryType={deliveryType}
                        setDeliveryType={setDeliveryType}
                        customerAddress={customerAddress}
                        setCustomerAddress={setCustomerAddress}
                        customerNotes={customerNotes}
                        setCustomerNotes={setCustomerNotes}
                        items={items}
                        grandTotal={grandTotal}
                        handleSubmitOrder={handleSubmitOrder}
                        isSubmitting={isSubmitting}
                        submitError={submitError}
                        errors={errors}
                    />
                )}
            </SheetContent>
            </Sheet>
        </div>
    );
}
