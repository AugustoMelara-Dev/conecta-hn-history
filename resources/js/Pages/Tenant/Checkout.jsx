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

import { useMemo, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { toast as sonnerToast } from 'sonner';
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
import { validateHondurasPhone } from '@/Utils/validators';
import ImageWithFallback from '@/Components/ImageWithFallback';
import ConversionActionBar from '@/Components/Conversion/ConversionActionBar';
import ConversionSummary from '@/Components/Conversion/ConversionSummary';
import { focusFirstInvalidField, FormRecoveryAlert } from '@/Components/Conversion/FormRecovery';
import { ActionableAlert, EmptyState, LoadingState } from '@/Components/Feedback/UXStates';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Textarea } from '@/Components/ui/textarea';
import { cn } from '@/lib/utils';

function CheckoutBreadcrumbs({ tenant }) {
    const tenantHref = tenant.slug ? `/t/${tenant.slug}` : '/';

    return (
        <nav aria-label="Ubicación" className="mb-6 text-sm">
            <ol className="flex flex-wrap items-center gap-2 text-muted-foreground">
                <li>
                    <Link href={tenantHref} className="font-medium transition-colors hover:text-foreground">
                        Inicio del negocio
                    </Link>
                </li>
                <li aria-hidden="true">/</li>
                <li>
                    <Link href={tenantHref} className="font-medium transition-colors hover:text-foreground">
                        Catálogo
                    </Link>
                </li>
                <li aria-hidden="true">/</li>
                <li className="font-semibold text-foreground">Checkout</li>
            </ol>
        </nav>
    );
}

/**
 * CartItemRow - Single item in order summary with self-healing image
 */
function CartItemRow({ item, primaryColor, onRemove }) {
    const modifiersTotal = (item.selectedModifiers || []).reduce(
        (sum, m) => sum + (m.price || 0),
        0
    );
    const itemTotal = (item.price + modifiersTotal) * item.quantity;

    return (
        <div className="flex items-start gap-4 border-b border-border py-4 last:border-0">
            {/* Always show image - fallback to icon if no image */}
            <ImageWithFallback
                src={item.image || ''}
                fallbackIcon
                fallbackColor={`${primaryColor}15`}
                alt={item.name}
                className="h-16 w-16 rounded-lg object-cover"
                loading="lazy"
            />
            <div className="min-w-0 flex-1">
                <h4 className="font-medium text-foreground">{item.name}</h4>
                <p className="text-sm text-muted-foreground">Cantidad: {item.quantity}</p>
                {item.selectedModifiers?.length > 0 && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {item.selectedModifiers.map((m) => m.name).join(', ')}
                    </p>
                )}
            </div>
            <div className="text-right">
                <p className="font-semibold" style={{ color: primaryColor }}>
                    {formatCurrency(itemTotal)}
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => onRemove(item.id)}
                    className="mt-1 h-8 w-8 text-muted-foreground hover:text-destructive"
                    aria-label={`Eliminar ${item.name}`}
                >
                    <Trash2 className="h-4 w-4" data-icon aria-hidden="true" />
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
    const [hasSubmitAttempted, setHasSubmitAttempted] = useState(false);

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
        submitDiagnostic,
        submitOrder,
        clearError,
    } = useCartStore();

    const subtotal = getSubtotal();
    const grandTotal = getGrandTotal();

    // ═══════════════════════════════════════════════════════════════════════
    // PHONE VALIDATION: Using centralized validator from Utils/validators
    // ═══════════════════════════════════════════════════════════════════════
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

    // ═══════════════════════════════════════════════════════════════════════
    // Form validation with useMemo - uses centralized validator
    // ═══════════════════════════════════════════════════════════════════════
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

    const isCustomerNameInvalid = hasSubmitAttempted && (!customerName || customerName.trim().length < 2);
    const isCustomerPhoneMissing = hasSubmitAttempted && (!customerPhone || customerPhone.trim().length < 8);
    const isCustomerPhoneInvalid = Boolean(phoneError) || isCustomerPhoneMissing;
    const isDeliveryAddressInvalid = hasSubmitAttempted
        && deliveryType === 'delivery'
        && (!customerAddress || customerAddress.trim().length < 5);
    const checkoutRecoveryMessages = [
        isCustomerNameInvalid ? 'Agrega el nombre completo de quien recibe el pedido.' : null,
        isCustomerPhoneInvalid ? (phoneError || 'Ingresa un telefono valido para coordinar el pedido.') : null,
        isDeliveryAddressInvalid ? 'Agrega una direccion o referencia clara para el delivery.' : null,
    ];
    const focusCheckoutFirstError = () => focusFirstInvalidField(document.querySelector('[data-testid="checkout-form"]'));
    const checkoutTotals = [
        { label: 'Subtotal', value: formatCurrency(subtotal) },
        { label: 'Total', value: formatCurrency(grandTotal), emphasis: true, accent: true },
    ];
    const checkoutProgressValue = isSubmitting ? 70 : isFormValid ? 100 : 45;
    const checkoutProgressLabel = isSubmitting ? 'Enviando pedido' : 'Progreso del checkout';
    const checkoutProgressStatus = isSubmitting ? 'Procesando' : isFormValid ? 'Listo' : 'Pendiente';

    // ═══════════════════════════════════════════════════════════════════════
    // BEACON TELEMETRY: Send conversion event with graceful degradation
    // ═══════════════════════════════════════════════════════════════════════
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

    const showToast = (message, type = 'success') => {
        const notify = type === 'error'
            ? sonnerToast.error
            : type === 'warning'
                ? sonnerToast.warning
                : sonnerToast.success;

        notify(message);
    };

    // ═══════════════════════════════════════════════════════════════════════
    // ORDER SUBMISSION: With Guard Clause & Beacon Telemetry
    // ═══════════════════════════════════════════════════════════════════════
    const handleSubmit = async (e) => {
        e.preventDefault();
        setHasSubmitAttempted(true);

        // ═══════════════════════════════════════════════════════════════════
        // GUARD CLAUSE: Prevent race condition
        // ═══════════════════════════════════════════════════════════════════
        if (isSubmitting) {
            return;
        }

        // Additional validation guard
        if (!isFormValid) {
            // Show phone error if that's the issue
            if (customerPhone && customerPhone.trim().length >= 8) {
                handlePhoneValidation(customerPhone);
            }
            window.setTimeout(focusCheckoutFirstError, 0);
            showToast('Completa los campos requeridos para confirmar el pedido.', 'warning');
            return;
        }

        clearError();

        const result = await submitOrder();

        if (result.success && result.whatsappUrl) {
            // ═══════════════════════════════════════════════════════════════
            // BEACON TELEMETRY: Fire conversion events before redirect
            // Generate a pseudo-transaction ID from timestamp
            // ═══════════════════════════════════════════════════════════════
            const transactionId = `${tenant.slug}-${Date.now()}`;
            sendConversionBeacon(transactionId, grandTotal);

            // Show success toast briefly
            showToast('¡Pedido enviado! Redirigiendo a WhatsApp...', 'success');

            // ═══════════════════════════════════════════════════════════════
            // SAFETY NET: 300ms delay for analytics pixels to fire
            // Allows GA4, Meta Pixel, etc. to complete execution
            // ═══════════════════════════════════════════════════════════════
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

    // ═══════════════════════════════════════════════════════════════════════
    // EMPTY CART STATE
    // ═══════════════════════════════════════════════════════════════════════
    if (!isInitialized) {
        return (
            <StoreLayout>
                <Head title="Checkout" />
                <div className="max-w-7xl mx-auto px-4 py-16">
                    <LoadingState
                        title="Preparando checkout"
                        description="Estamos sincronizando el negocio y tu carrito antes de mostrar el formulario."
                        testId="checkout-loading-state"
                    />
                </div>
            </StoreLayout>
        );
    }

    if (items.length === 0) {
        return (
            <StoreLayout>
                <Head title="Checkout" />
                <div className="max-w-7xl mx-auto px-4 py-16">
                    <CheckoutBreadcrumbs tenant={tenant} />
                    <EmptyState
                        icon={ShoppingCart}
                        eyebrow="Checkout"
                        title="Tu carrito está vacío"
                        description="Agrega productos desde el catálogo del negocio para continuar con tu pedido."
                        actionLabel="Ver catálogo"
                        actionHref={tenant.slug ? `/t/${tenant.slug}` : '/'}
                        testId="checkout-empty-cart"
                    />
                </div>
            </StoreLayout>
        );
    }

    return (
        <StoreLayout>
            <Head title="Finalizar Pedido" />

            <div className="max-w-7xl mx-auto px-4 py-6">
                <CheckoutBreadcrumbs tenant={tenant} />

                {/* Back Link */}
                <Link
                    href={tenant.slug ? `/t/${tenant.slug}` : '/'}
                    className="mb-6 inline-flex items-center text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" data-icon aria-hidden="true" />
                    Seguir comprando
                </Link>

                <h1 className="mb-8 text-3xl font-bold text-foreground">
                    Finalizar Pedido
                </h1>

                <form
                    onSubmit={handleSubmit}
                    noValidate
                    data-testid="checkout-form"
                    style={{
                        '--checkout-accent': primaryColor,
                        '--checkout-accent-soft': `${primaryColor}12`,
                    }}
                >
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* ═══════════════════════════════════════════════════
                            LEFT: Customer Form
                        ═══════════════════════════════════════════════════ */}
                        <div className="grid gap-6 lg:col-span-2">
                            <FormRecoveryAlert
                                messages={checkoutRecoveryMessages}
                                onFocusFirstError={focusCheckoutFirstError}
                                testId="checkout-recovery-alert"
                            />

                            {/* Customer Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <User className="h-5 w-5 text-[color:var(--checkout-accent)]" data-icon aria-hidden="true" />
                                        Información de contacto
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <FieldGroup className="gap-5">
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <Field data-invalid={isCustomerNameInvalid ? 'true' : undefined}>
                                                <FieldLabel htmlFor="name">Nombre *</FieldLabel>
                                                <Input
                                                    type="text"
                                                    id="name"
                                                    name="customer_name"
                                                    data-testid="checkout-name-input"
                                                    value={customerName}
                                                    onChange={(e) => setCustomerName(e.target.value)}
                                                    onBlur={(e) => setCustomerName(e.target.value.trim())}
                                                    placeholder="Tu nombre"
                                                    className={cn('h-12', isCustomerNameInvalid && 'border-destructive focus-visible:ring-destructive')}
                                                    required
                                                    minLength={2}
                                                    maxLength={100}
                                                    aria-required="true"
                                                    aria-label="Nombre completo"
                                                    aria-invalid={isCustomerNameInvalid ? 'true' : 'false'}
                                                    aria-describedby={isCustomerNameInvalid ? 'name-error' : undefined}
                                                    autoComplete="name"
                                                />
                                                {isCustomerNameInvalid ? (
                                                    <FieldDescription
                                                        id="name-error"
                                                        role="alert"
                                                        className="text-destructive"
                                                    >
                                                        Ingresa tu nombre completo para preparar el pedido.
                                                    </FieldDescription>
                                                ) : null}
                                            </Field>

                                            <Field data-invalid={isCustomerPhoneInvalid ? 'true' : undefined}>
                                                <FieldLabel htmlFor="phone">Teléfono *</FieldLabel>
                                                <div className="relative">
                                                    <Phone className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" data-icon aria-hidden="true" />
                                                    <Input
                                                        type="tel"
                                                        id="phone"
                                                        name="customer_phone"
                                                        data-testid="checkout-phone-input"
                                                        value={customerPhone}
                                                        onChange={handlePhoneChange}
                                                        onBlur={handlePhoneBlur}
                                                        placeholder="9999-1234"
                                                        className={cn('h-12 pl-10 pr-10', isCustomerPhoneInvalid && 'border-destructive focus-visible:ring-destructive')}
                                                        required
                                                        minLength={8}
                                                        maxLength={15}
                                                        aria-required="true"
                                                        aria-label="Número de teléfono celular"
                                                        aria-invalid={isCustomerPhoneInvalid ? 'true' : 'false'}
                                                        aria-describedby={isCustomerPhoneInvalid ? 'phone-error' : 'phone-help'}
                                                        autoComplete="tel"
                                                        inputMode="tel"
                                                    />
                                                    {isPhoneValid && !phoneError && (
                                                        <CheckCircle
                                                            className="absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-[color:var(--ds-success)]"
                                                            data-icon
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                </div>
                                                {isCustomerPhoneInvalid ? (
                                                    <FieldDescription
                                                        id="phone-error"
                                                        role="alert"
                                                        aria-live="polite"
                                                        className="flex items-center gap-1 text-destructive"
                                                    >
                                                        <AlertCircle className="h-4 w-4 shrink-0" data-icon aria-hidden="true" />
                                                        <span>{phoneError || 'Ingresa un teléfono válido para coordinar tu pedido.'}</span>
                                                    </FieldDescription>
                                                ) : (
                                                    <FieldDescription id="phone-help">
                                                        Ejemplo: 9999-1234, +504 8888-1234
                                                    </FieldDescription>
                                                )}
                                            </Field>
                                        </div>

                                        <Field>
                                            <FieldLabel htmlFor="email">Email (opcional)</FieldLabel>
                                            <div className="relative">
                                                <Mail className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" data-icon aria-hidden="true" />
                                                <Input
                                                    type="email"
                                                    id="email"
                                                    name="customer_email"
                                                    value={customerEmail}
                                                    onChange={(e) => setCustomerEmail(e.target.value)}
                                                    onBlur={(e) => setCustomerEmail(e.target.value.trim())}
                                                    placeholder="tu@email.com"
                                                    className="h-12 pl-10"
                                                    maxLength={100}
                                                    aria-label="Correo electrónico"
                                                    autoComplete="email"
                                                    inputMode="email"
                                                />
                                            </div>
                                        </Field>
                                    </FieldGroup>
                                </CardContent>
                            </Card>

                            {/* Delivery Options */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <Truck className="h-5 w-5 text-[color:var(--checkout-accent)]" data-icon aria-hidden="true" />
                                        Método de entrega
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <FieldGroup className="gap-5">
                                        <RadioGroup
                                            name="delivery_type"
                                            value={deliveryType}
                                            onValueChange={setDeliveryType}
                                            className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                                            aria-label="Método de entrega"
                                        >
                                            <label
                                                htmlFor="delivery-pickup"
                                                className={cn(
                                                    'flex cursor-pointer items-center gap-3 rounded-lg border p-4 text-left transition-colors',
                                                    'focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
                                                    deliveryType === 'pickup' && 'border-[color:var(--checkout-accent)] bg-[color:var(--checkout-accent-soft)] text-foreground'
                                                )}
                                            >
                                                <RadioGroupItem
                                                    id="delivery-pickup"
                                                    value="pickup"
                                                    className="shrink-0 data-[state=checked]:border-[color:var(--checkout-accent)] data-[state=checked]:text-[color:var(--checkout-accent)]"
                                                />
                                                <Store className="h-5 w-5 shrink-0" data-icon aria-hidden="true" />
                                                <span className="min-w-0 font-medium">Recoger en tienda</span>
                                            </label>
                                            <label
                                                htmlFor="delivery-address"
                                                className={cn(
                                                    'flex cursor-pointer items-center gap-3 rounded-lg border p-4 text-left transition-colors',
                                                    'focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
                                                    deliveryType === 'delivery' && 'border-[color:var(--checkout-accent)] bg-[color:var(--checkout-accent-soft)] text-foreground'
                                                )}
                                            >
                                                <RadioGroupItem
                                                    id="delivery-address"
                                                    value="delivery"
                                                    className="shrink-0 data-[state=checked]:border-[color:var(--checkout-accent)] data-[state=checked]:text-[color:var(--checkout-accent)]"
                                                />
                                                <Truck className="h-5 w-5 shrink-0" data-icon aria-hidden="true" />
                                                <span className="min-w-0 font-medium">Delivery a domicilio</span>
                                            </label>
                                        </RadioGroup>

                                        {deliveryType === 'delivery' && (
                                            <Field data-invalid={isDeliveryAddressInvalid ? 'true' : undefined}>
                                                <FieldLabel htmlFor="address">Dirección de entrega *</FieldLabel>
                                                <div className="relative">
                                                    <MapPin className="absolute left-3 top-3 h-5 w-5 text-muted-foreground" data-icon aria-hidden="true" />
                                                    <Textarea
                                                        id="address"
                                                        name="customer_address"
                                                        data-testid="checkout-address-input"
                                                        value={customerAddress}
                                                        onChange={(e) => setCustomerAddress(e.target.value)}
                                                        onBlur={(e) => setCustomerAddress(e.target.value.trim())}
                                                        placeholder="Colonia, calle, número de casa, referencias..."
                                                        rows={3}
                                                        className={cn('pl-10', isDeliveryAddressInvalid && 'border-destructive focus-visible:ring-destructive')}
                                                        required={deliveryType === 'delivery'}
                                                        minLength={5}
                                                        maxLength={300}
                                                        aria-required="true"
                                                        aria-label="Dirección de entrega"
                                                        aria-invalid={isDeliveryAddressInvalid ? 'true' : 'false'}
                                                        aria-describedby={isDeliveryAddressInvalid ? 'address-error' : 'address-help'}
                                                        autoComplete="street-address"
                                                    />
                                                </div>
                                                {isDeliveryAddressInvalid ? (
                                                    <FieldDescription
                                                        id="address-error"
                                                        role="alert"
                                                        className="text-destructive"
                                                    >
                                                        Agrega una dirección o referencia de al menos 5 caracteres.
                                                    </FieldDescription>
                                                ) : (
                                                    <FieldDescription id="address-help">
                                                        Incluye una referencia clara para facilitar la entrega.
                                                    </FieldDescription>
                                                )}
                                            </Field>
                                        )}
                                    </FieldGroup>
                                </CardContent>
                            </Card>

                            {/* Notes */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <FileText className="h-5 w-5 text-[color:var(--checkout-accent)]" data-icon aria-hidden="true" />
                                        Notas adicionales
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Field>
                                        <FieldLabel htmlFor="checkout-notes">Indicaciones para el negocio</FieldLabel>
                                        <Textarea
                                            id="checkout-notes"
                                            name="customer_notes"
                                            data-testid="checkout-notes-input"
                                            value={customerNotes}
                                            onChange={(e) => setCustomerNotes(e.target.value)}
                                            onBlur={(e) => setCustomerNotes(e.target.value.trim())}
                                            placeholder="Instrucciones especiales para tu pedido..."
                                            rows={3}
                                            maxLength={500}
                                            aria-label="Notas adicionales para el pedido"
                                        />
                                        <FieldDescription>
                                            Puedes agregar horarios, sustituciones o detalles de entrega.
                                        </FieldDescription>
                                    </Field>
                                </CardContent>
                            </Card>
                        </div>

                        {/* ═══════════════════════════════════════════════════
                            RIGHT: Order Summary
                        ═══════════════════════════════════════════════════ */}
                        <div className="lg:col-span-1">
                            <ConversionSummary
                                icon={ShoppingCart}
                                title="Resumen del pedido"
                                totals={checkoutTotals}
                                progressLabel={checkoutProgressLabel}
                                progressStatus={checkoutProgressStatus}
                                progressValue={checkoutProgressValue}
                            >
                                    <div className="max-h-80 overflow-y-auto pr-1" role="list" aria-label="Productos en el carrito">
                                        {items.map((item) => (
                                            <CartItemRow
                                                key={item.id}
                                                item={item}
                                                primaryColor={primaryColor}
                                                onRemove={removeItem}
                                            />
                                        ))}
                                    </div>

                                {/* Error Message with ref for scroll */}
                                {submitError && (
                                    <div ref={errorRef}>
                                        <ActionableAlert
                                            icon={AlertCircle}
                                            tone="error"
                                            title="No pudimos confirmar el pedido"
                                            actionLabel="Revisar datos"
                                            onAction={() => errorRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })}
                                            className="mt-4"
                                            testId="checkout-submit-error"
                                        >
                                            <p>{submitError}</p>
                                            {submitDiagnostic?.recommended_action ? (
                                                <p className="mt-1">{submitDiagnostic.recommended_action}</p>
                                            ) : (
                                                <p className="mt-1">Verifica los datos del cliente e intenta nuevamente.</p>
                                            )}
                                            {submitDiagnostic?.trace_id ? (
                                                <p className="mt-2 text-xs font-semibold">REF soporte: {submitDiagnostic.trace_id}</p>
                                            ) : null}
                                        </ActionableAlert>
                                    </div>
                                )}

                                <ConversionActionBar
                                    disabled={!isFormValid || isSubmitting}
                                    busy={isSubmitting}
                                    busyLabel="Guardando pedido..."
                                    idleLabel="Confirmar Pedido"
                                    icon={CheckCircle}
                                    processingIcon={Loader2}
                                    style={{ backgroundColor: primaryColor }}
                                    testId="checkout-submit"
                                >
                                    Al confirmar, seras redirigido a WhatsApp para finalizar tu pedido.
                                </ConversionActionBar>
                            </ConversionSummary>
                        </div>
                    </div>
                </form>
            </div>
        </StoreLayout>
    );
}
