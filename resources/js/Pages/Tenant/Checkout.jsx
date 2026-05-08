/**
 * Checkout.jsx - The Transactional Terminal (ENTERPRISE v4)
 *
 * ARCHITECTURE:
 * - Global Store Binding (no local state for form fields)
 * - Guard clause pattern for race condition prevention
 * - Empty cart redirect protection with hydration check
 * - WCAG-compliant form accessibility
 * - Centralized Honduras phone validation from Utils/validators
 * - Graceful Beacon API for non-blocking analytics
 *
 * SECURITY:
 * - Prevents double-submission via guard clause
 * - Validates form with centralized regex before submission
 * - Protects against empty cart submission
 * - Waits for tenant initialization before redirecting
 *
 * ANALYTICS:
 * - Beacon API for conversion tracking (graceful degradation)
 * - 300ms redirect delay for pixel firing
 * - GTM/fbq integration for conversion pixels
 */

import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ShoppingCart,
    User,
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
import { validateHondurasPhone } from '@/Utils/validators';
import ImageWithFallback from '@/Components/ImageWithFallback';
import Toast from '@/Components/Toast';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import { Spinner } from '@/Components/ui/spinner';
import { Textarea } from '@/Components/ui/textarea';

/**
 * CartItemRow - Single item in order summary with self-healing image
 */
function CartItemRow({ item, onRemove }) {
    const modifiersTotal = (item.selectedModifiers || []).reduce(
        (sum, m) => sum + (m.price || 0),
        0
    );
    const itemTotal = (item.price + modifiersTotal) * item.quantity;

    return (
        <div className="flex items-start gap-4 py-4">
            {/* Always show image - fallback to icon if no image */}
            <ImageWithFallback
                src={item.image || ''}
                fallbackIcon
                fallbackColor="hsl(var(--muted))"
                alt={item.name}
                className="size-16 rounded-lg object-cover"
                loading="lazy"
            />
            <div className="flex-1 min-w-0">
                <h4 className="font-medium text-foreground">{item.name}</h4>
                <p className="text-sm text-muted-foreground">Cantidad: {item.quantity}</p>
                {item.selectedModifiers?.length > 0 && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {item.selectedModifiers.map((m) => m.name).join(', ')}
                    </p>
                )}
            </div>
            <div className="text-right">
                <p className="font-semibold text-primary">
                    {formatCurrency(itemTotal)}
                </p>
                <Button
                    type="button"
                    onClick={() => onRemove(item.id)}
                    variant="ghost"
                    size="icon"
                    className="mt-1"
                    aria-label={`Eliminar ${item.name}`}
                >
                    <Trash2 aria-hidden="true" />
                </Button>
            </div>
        </div>
    );
}

/**
 * Checkout - Main Checkout Page
 */
export default function Checkout() {
    // Use centralized tenant context hook - includes isInitialized
    const { tenant, primaryColor, isInitialized } = useTenantContext();

    // Error element ref for scroll behavior
    const errorRef = useRef(null);

    // Phone validation error state
    const [phoneError, setPhoneError] = useState('');
    // Phone validation success state (for positive feedback)
    const [isPhoneValid, setIsPhoneValid] = useState(false);

    // Toast state with timeout ref for cleanup
    const [toast, setToast] = useState(null);
    const toastTimeoutRef = useRef(null);
    // CLEANUP: Clear toast timeout on unmount
    useEffect(() => {
        return () => {
            if (toastTimeoutRef.current) {
                clearTimeout(toastTimeoutRef.current);
            }
        };
    }, []);
    // GLOBAL STORE BINDING: No local state - everything from Zustand
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
    // EMPTY CART DEFENSE: Redirect if cart becomes empty
    // Waits for tenant initialization to prevent premature redirect
    useEffect(() => {
        if (isInitialized && items.length === 0 && !isSubmitting && tenant.slug) {
            const timeout = setTimeout(() => {
                router.visit(`/${tenant.slug}`);
            }, 100);

            return () => clearTimeout(timeout);
        }
    }, [items.length, isSubmitting, tenant.slug, isInitialized]);
    // PHONE VALIDATION: Using centralized validator from Utils/validators
    const handlePhoneValidation = (phone) => {
        const result = validateHondurasPhone(phone);

        if (result.isValid) {
            setPhoneError('');
            setIsPhoneValid(true);
            return true;
        }

        // Don't show error for empty or short inputs (handled by isFormValid)
        if (!phone || phone.trim().length < 8) {
            setPhoneError('');
            setIsPhoneValid(false);
            return false;
        }

        // Show specific error from centralized validator
        setPhoneError(result.error);
        setIsPhoneValid(false);
        return false;
    };

    // Handle phone change with validation
    const handlePhoneChange = (e) => {
        const value = e.target.value;
        setCustomerPhone(value);

        // Clear states while typing
        if (phoneError) {
            setPhoneError('');
        }
        if (isPhoneValid) {
            setIsPhoneValid(false);
        }
    };

    // Handle phone blur for validation
    const handlePhoneBlur = () => {
        if (customerPhone && customerPhone.trim().length >= 8) {
            handlePhoneValidation(customerPhone);
        }
    };
    // Form validation with useMemo - uses centralized validator
    const isFormValid = useMemo(() => {
        if (items.length === 0) return false;
        if (!customerName || customerName.trim().length < 2) return false;
        if (!customerPhone || customerPhone.trim().length < 8) return false;

        // Use centralized Honduras phone validation
        const phoneResult = validateHondurasPhone(customerPhone);
        if (!phoneResult.isValid) return false;

        if (deliveryType === 'delivery' && (!customerAddress || customerAddress.trim().length < 5)) {
            return false;
        }
        return true;
    }, [items.length, customerName, customerPhone, deliveryType, customerAddress]);
    // BEACON TELEMETRY: Send conversion event with graceful degradation
    const sendConversionBeacon = (transactionId, value) => {
        const payload = {
            event: 'purchase',
            transaction_id: transactionId,
            value: value,
            currency: 'HNL',
            tenant_slug: tenant.slug,
            timestamp: new Date().toISOString(),
        };

        // Feature detection for Beacon API
        if (typeof navigator !== 'undefined' && navigator.sendBeacon) {
            try {
                const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
                // Send to analytics endpoint - graceful failure if not configured
                navigator.sendBeacon('/api/analytics/conversion', blob);
            } catch (e) {
                // Silently fail in production, log in development
                if (process.env.NODE_ENV === 'development') {
                    console.log('[Analytics] Beacon failed:', e);
                }
            }
        }

        // Also emit to dataLayer for GTM/GA4 if available
        if (typeof window !== 'undefined' && window.dataLayer) {
            window.dataLayer.push({
                event: 'purchase',
                ecommerce: {
                    transaction_id: transactionId,
                    value: value,
                    currency: 'HNL',
                },
            });
        }

        // Emit to fbq if Meta Pixel is loaded
        if (typeof window !== 'undefined' && window.fbq) {
            window.fbq('track', 'Purchase', {
                value: value,
                currency: 'HNL',
            });
        }
    };

    // Toast helpers with timeout cleanup
    const showToast = (message, type = 'success') => {
        if (toastTimeoutRef.current) {
            clearTimeout(toastTimeoutRef.current);
        }

        setToast({ message, type });

        toastTimeoutRef.current = setTimeout(() => {
            setToast(null);
            toastTimeoutRef.current = null;
        }, 4000);
    };

    const hideToast = () => {
        if (toastTimeoutRef.current) {
            clearTimeout(toastTimeoutRef.current);
            toastTimeoutRef.current = null;
        }
        setToast(null);
    };
    // ORDER SUBMISSION: With Guard Clause & Beacon Telemetry
    const handleSubmit = async (e) => {
        e.preventDefault();
        // GUARD CLAUSE: Prevent race condition
        if (isSubmitting) {
            return;
        }

        // Additional validation guard
        if (!isFormValid) {
            // Show phone error if that's the issue
            if (customerPhone && customerPhone.trim().length >= 8) {
                handlePhoneValidation(customerPhone);
            }
            return;
        }

        clearError();

        const result = await submitOrder();

        if (result.success && result.whatsappUrl) {
            // BEACON TELEMETRY: Fire conversion events before redirect
            // Generate a pseudo-transaction ID from timestamp
            const transactionId = `${tenant.slug}-${Date.now()}`;
            sendConversionBeacon(transactionId, grandTotal);

            // Show success toast briefly
            showToast('¡Pedido enviado! Redirigiendo a WhatsApp...', 'success');
            // SAFETY NET: 300ms delay for analytics pixels to fire
            // Allows GA4, Meta Pixel, etc. to complete execution
            setTimeout(() => {
                window.location.href = result.whatsappUrl;
            }, 300);
        } else if (!result.success) {
            // Scroll to error message
            setTimeout(() => {
                errorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        }
    };
    // EMPTY CART STATE (Fallback before redirect)
    if (items.length === 0) {
        return (
            <StoreLayout>
                <Head title="Checkout" />
                                <div className="mx-auto max-w-2xl px-4 py-16">
                    <Card className="text-center">
                        <CardHeader>
                            <ShoppingCart className="mx-auto mb-2 size-14 text-muted-foreground" aria-hidden="true" />
                            <CardTitle>Tu carrito esta vacio</CardTitle>
                            <CardDescription>
                                Agrega productos para continuar con tu pedido.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild>
                                <Link href={`/${tenant.slug || ''}`}>
                                    <ArrowLeft data-icon="inline-start" aria-hidden="true" />
                                    Ver catalogo
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </StoreLayout>
        );
    }

    return (
        <StoreLayout>
            <Head title="Finalizar Pedido" />

            {/* Toast Notification - Centralized component */}
            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={hideToast}
                    primaryColor={primaryColor}
                />
            )}

            <div className="max-w-7xl mx-auto px-4 py-6">
                {/* Back Link */}
                <Link
                    href={`/${tenant.slug}`}
                    className="inline-flex items-center text-gray-600 hover:text-gray-900 mb-6"
                >
                    <ArrowLeft className="h-4 w-4 mr-2" aria-hidden="true" />
                    Seguir comprando
                </Link>

                <h1 className="text-3xl font-bold text-gray-900 mb-8">
                    Finalizar Pedido
                </h1>

                <form onSubmit={handleSubmit} noValidate>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">                                                <div className="flex flex-col gap-6 lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <User aria-hidden="true" />
                                        Informacion de contacto
                                    </CardTitle>
                                    <CardDescription>
                                        Datos necesarios para confirmar el pedido por WhatsApp.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <FieldGroup>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <Field>
                                                <FieldLabel htmlFor="name">Nombre *</FieldLabel>
                                                <Input
                                                    type="text"
                                                    id="name"
                                                    value={customerName}
                                                    onChange={(e) => setCustomerName(e.target.value)}
                                                    onBlur={(e) => setCustomerName(e.target.value.trim())}
                                                    placeholder="Tu nombre"
                                                    required
                                                    minLength={2}
                                                    maxLength={100}
                                                    aria-required="true"
                                                    aria-label="Nombre completo"
                                                    autoComplete="name"
                                                />
                                            </Field>

                                            <Field data-invalid={Boolean(phoneError)}>
                                                <FieldLabel htmlFor="phone">Telefono *</FieldLabel>
                                                <Input
                                                    type="tel"
                                                    id="phone"
                                                    value={customerPhone}
                                                    onChange={handlePhoneChange}
                                                    onBlur={handlePhoneBlur}
                                                    placeholder="9999-1234"
                                                    required
                                                    minLength={8}
                                                    maxLength={15}
                                                    aria-required="true"
                                                    aria-label="Numero de telefono celular"
                                                    aria-invalid={phoneError ? 'true' : 'false'}
                                                    aria-describedby={phoneError ? 'phone-error' : 'phone-help'}
                                                    autoComplete="tel"
                                                />
                                                {phoneError ? (
                                                    <FieldDescription id="phone-error" role="alert" aria-live="polite" className="text-destructive">
                                                        {phoneError}
                                                    </FieldDescription>
                                                ) : (
                                                    <FieldDescription id="phone-help">
                                                        Ejemplo: 9999-1234, +504 8888-1234
                                                    </FieldDescription>
                                                )}
                                                {isPhoneValid && !phoneError ? (
                                                    <FieldDescription className="text-primary">
                                                        Telefono validado.
                                                    </FieldDescription>
                                                ) : null}
                                            </Field>
                                        </div>

                                        <Field>
                                            <FieldLabel htmlFor="email">Email (opcional)</FieldLabel>
                                            <Input
                                                type="email"
                                                id="email"
                                                value={customerEmail}
                                                onChange={(e) => setCustomerEmail(e.target.value)}
                                                onBlur={(e) => setCustomerEmail(e.target.value.trim())}
                                                placeholder="tu@email.com"
                                                maxLength={100}
                                                aria-label="Correo electronico"
                                                autoComplete="email"
                                            />
                                        </Field>
                                    </FieldGroup>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Truck aria-hidden="true" />
                                        Metodo de entrega
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <FieldGroup>
                                        <div className="grid grid-cols-2 gap-4" role="radiogroup" aria-label="Metodo de entrega">
                                            <Button
                                                type="button"
                                                variant={deliveryType === 'pickup' ? 'default' : 'outline'}
                                                onClick={() => setDeliveryType('pickup')}
                                                role="radio"
                                                aria-checked={deliveryType === 'pickup'}
                                                aria-label="Recoger en tienda"
                                            >
                                                <Store data-icon="inline-start" aria-hidden="true" />
                                                Recoger
                                            </Button>
                                            <Button
                                                type="button"
                                                variant={deliveryType === 'delivery' ? 'default' : 'outline'}
                                                onClick={() => setDeliveryType('delivery')}
                                                role="radio"
                                                aria-checked={deliveryType === 'delivery'}
                                                aria-label="Delivery a domicilio"
                                            >
                                                <Truck data-icon="inline-start" aria-hidden="true" />
                                                Delivery
                                            </Button>
                                        </div>

                                        {deliveryType === 'delivery' && (
                                            <Field>
                                                <FieldLabel htmlFor="address">Direccion de entrega *</FieldLabel>
                                                <Textarea
                                                    id="address"
                                                    value={customerAddress}
                                                    onChange={(e) => setCustomerAddress(e.target.value)}
                                                    onBlur={(e) => setCustomerAddress(e.target.value.trim())}
                                                    placeholder="Colonia, calle, numero de casa, referencias..."
                                                    rows={3}
                                                    required={deliveryType === 'delivery'}
                                                    minLength={5}
                                                    maxLength={300}
                                                    aria-required="true"
                                                    aria-label="Direccion de entrega"
                                                    autoComplete="street-address"
                                                />
                                            </Field>
                                        )}
                                    </FieldGroup>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText aria-hidden="true" />
                                        Notas adicionales
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Field>
                                        <FieldLabel htmlFor="checkout-notes">Instrucciones especiales</FieldLabel>
                                        <Textarea
                                            id="checkout-notes"
                                            value={customerNotes}
                                            onChange={(e) => setCustomerNotes(e.target.value)}
                                            onBlur={(e) => setCustomerNotes(e.target.value.trim())}
                                            placeholder="Instrucciones especiales para tu pedido..."
                                            rows={3}
                                            maxLength={500}
                                            aria-label="Notas adicionales para el pedido"
                                        />
                                    </Field>
                                </CardContent>
                            </Card>
                        </div>

                                                <div className="lg:col-span-1">
                            <Card className="sticky top-40">
                                <CardHeader>
                                    <CardTitle>Resumen del pedido</CardTitle>
                                    <CardDescription>{items.length} producto(s) en el carrito.</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-4">
                                    <div className="max-h-80 overflow-y-auto" role="list" aria-label="Productos en el carrito">
                                        {items.map((item, index) => (
                                            <div key={item.id}>
                                                <CartItemRow item={item} onRemove={removeItem} />
                                                {index < items.length - 1 ? <Separator /> : null}
                                            </div>
                                        ))}
                                    </div>

                                    <Separator />

                                    <div className="flex flex-col gap-2">
                                        <div className="flex justify-between text-muted-foreground">
                                            <span>Subtotal</span>
                                            <span>{formatCurrency(subtotal)}</span>
                                        </div>
                                        <div className="flex justify-between text-xl font-bold text-foreground">
                                            <span>Total</span>
                                            <span className="text-primary">{formatCurrency(grandTotal)}</span>
                                        </div>
                                    </div>

                                    {submitError && (
                                        <Alert ref={errorRef} variant="destructive" role="alert">
                                            <AlertCircle aria-hidden="true" />
                                            <AlertDescription>{submitError}</AlertDescription>
                                        </Alert>
                                    )}

                                    <Button
                                        type="submit"
                                        disabled={!isFormValid || isSubmitting}
                                        size="lg"
                                        className="w-full"
                                        aria-label={isSubmitting ? 'Procesando pedido' : 'Confirmar pedido'}
                                        aria-busy={isSubmitting}
                                    >
                                        {isSubmitting ? (
                                            <>
                                                <Spinner data-icon="inline-start" aria-hidden="true" />
                                                Procesando...
                                            </>
                                        ) : (
                                            <>
                                                <CheckCircle data-icon="inline-start" aria-hidden="true" />
                                                Confirmar pedido
                                            </>
                                        )}
                                    </Button>

                                    <p className="text-center text-xs text-muted-foreground">
                                        Al confirmar, seras redirigido a WhatsApp para finalizar tu pedido.
                                    </p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </form>
            </div>
        </StoreLayout>
    );
}
