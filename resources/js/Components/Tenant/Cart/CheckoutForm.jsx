import React from 'react';
import { AlertCircle, User, Package, Truck, Check, MapPin, Loader2, MessageCircle, ShoppingCart } from 'lucide-react';
import { cn } from '@/lib/utils';
import { calculateItemTotal } from '@/Stores/useCartStore';
import SupportDiagnosticCard from '@/Components/SupportDiagnosticCard';
import ConversionActionBar from '@/Components/Conversion/ConversionActionBar';
import ConversionSummary from '@/Components/Conversion/ConversionSummary';
import { focusFirstInvalidField, FormRecoveryAlert } from '@/Components/Conversion/FormRecovery';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Field, FieldDescription, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';

export default function CheckoutForm({
    customerName,
    setCustomerName,
    customerPhone,
    setCustomerPhone,
    deliveryType,
    setDeliveryType,
    customerAddress,
    setCustomerAddress,
    customerNotes,
    setCustomerNotes,
    items,
    grandTotal,
    handleSubmitOrder,
    isSubmitting,
    submitError,
    submitDiagnostic,
    errors,
}) {
    const recoveryMessages = [
        errors.customerName ? 'Agrega el nombre de quien recibe el pedido.' : null,
        errors.customerPhone ? 'Agrega un telefono valido para coordinar el pedido.' : null,
        errors.customerAddress ? 'Agrega una direccion o referencia clara para el delivery.' : null,
    ];
    const focusDrawerFirstError = () => focusFirstInvalidField(document.querySelector('[data-testid="cart-drawer"]'));
    const drawerTotals = [
        { label: 'Total', value: `L. ${grandTotal.toFixed(2)}`, emphasis: true },
    ];

    return (
        <>
            <div className="min-h-0 flex-1 overflow-y-auto p-4">
                <div className="grid gap-5">
                {submitError ? (
                    <Alert variant="destructive" role="alert">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 flex-shrink-0" data-icon aria-hidden="true" />
                            <AlertDescription>{submitError}</AlertDescription>
                        </div>
                    </Alert>
                ) : null}

                <SupportDiagnosticCard diagnostic={submitDiagnostic} />

                <FormRecoveryAlert
                    messages={recoveryMessages}
                    onFocusFirstError={focusDrawerFirstError}
                    testId="cart-checkout-recovery-alert"
                />

                <Field>
                    <FieldLabel htmlFor="cart-checkout-name">
                        <User className="mr-2 inline h-4 w-4" data-icon aria-hidden="true" />
                        Tu nombre *
                    </FieldLabel>
                    <Input
                        id="cart-checkout-name"
                        type="text"
                        value={customerName}
                        onChange={(event) => setCustomerName(event.target.value)}
                        placeholder="Ej: María García"
                        data-testid="checkout-name-input"
                        aria-invalid={Boolean(errors.customerName)}
                        aria-describedby={errors.customerName ? 'cart-checkout-name-error' : undefined}
                        className={errors.customerName ? 'border-[color:var(--ds-danger)]' : ''}
                    />
                    {errors.customerName ? (
                        <p id="cart-checkout-name-error" className="text-sm text-[color:var(--ds-danger)]" role="alert">
                            {errors.customerName}
                        </p>
                    ) : null}
                </Field>

                <Field>
                    <FieldLabel htmlFor="cart-checkout-phone">Tu teléfono *</FieldLabel>
                    <Input
                        id="cart-checkout-phone"
                        type="tel"
                        value={customerPhone}
                        onChange={(event) => setCustomerPhone(event.target.value)}
                        placeholder="Ej: 9999-1234"
                        data-testid="checkout-phone-input"
                        aria-invalid={Boolean(errors.customerPhone)}
                        aria-describedby={errors.customerPhone ? 'cart-checkout-phone-error' : undefined}
                        className={errors.customerPhone ? 'border-[color:var(--ds-danger)]' : ''}
                    />
                    {errors.customerPhone ? (
                        <p id="cart-checkout-phone-error" className="text-sm text-[color:var(--ds-danger)]" role="alert">
                            {errors.customerPhone}
                        </p>
                    ) : null}
                </Field>

                <Field>
                    <FieldLabel>¿Cómo recibirás tu pedido?</FieldLabel>
                    <div className="grid grid-cols-2 gap-3">
                        <Button
                            type="button"
                            variant={deliveryType === 'pickup' ? 'default' : 'outline'}
                            onClick={() => setDeliveryType('pickup')}
                            data-testid="checkout-delivery-pickup"
                            className={cn(
                                'h-auto flex-col items-center gap-2 p-4',
                                deliveryType !== 'pickup' && 'bg-background'
                            )}
                        >
                            <Package className="h-6 w-6" data-icon aria-hidden="true" />
                            <span className="text-sm font-medium">
                                Recoger en tienda
                            </span>
                            {deliveryType === 'pickup' ? <Check className="h-4 w-4" data-icon aria-hidden="true" /> : null}
                        </Button>

                        <Button
                            type="button"
                            variant={deliveryType === 'delivery' ? 'default' : 'outline'}
                            onClick={() => setDeliveryType('delivery')}
                            data-testid="checkout-delivery-home"
                            className={cn(
                                'h-auto flex-col items-center gap-2 p-4',
                                deliveryType !== 'delivery' && 'bg-background'
                            )}
                        >
                            <Truck className="h-6 w-6" data-icon aria-hidden="true" />
                            <span className="text-sm font-medium">
                                A domicilio
                            </span>
                            {deliveryType === 'delivery' ? <Check className="h-4 w-4" data-icon aria-hidden="true" /> : null}
                        </Button>
                    </div>
                </Field>

                {deliveryType === 'delivery' ? (
                    <Field>
                        <FieldLabel htmlFor="cart-checkout-address">
                            <MapPin className="mr-2 inline h-4 w-4" data-icon aria-hidden="true" />
                            Dirección de entrega *
                        </FieldLabel>
                        <Textarea
                            id="cart-checkout-address"
                            value={customerAddress}
                            onChange={(event) => setCustomerAddress(event.target.value)}
                            placeholder="Colonia, calle, número de casa, referencias..."
                            rows={3}
                            data-testid="checkout-address-input"
                            aria-invalid={Boolean(errors.customerAddress)}
                            aria-describedby={errors.customerAddress ? 'cart-checkout-address-error' : undefined}
                            className={cn('resize-none', errors.customerAddress && 'border-[color:var(--ds-danger)]')}
                        />
                        {errors.customerAddress ? (
                            <p id="cart-checkout-address-error" className="text-sm text-[color:var(--ds-danger)]" role="alert">
                                {errors.customerAddress}
                            </p>
                        ) : null}
                    </Field>
                ) : null}

                <Field>
                    <FieldLabel htmlFor="cart-checkout-notes">Notas adicionales</FieldLabel>
                    <FieldDescription>Opcional. Puedes agregar instrucciones especiales o alergias.</FieldDescription>
                    <Textarea
                        id="cart-checkout-notes"
                        value={customerNotes}
                        onChange={(event) => setCustomerNotes(event.target.value)}
                        placeholder="Instrucciones especiales, alérgenos, etc."
                        rows={2}
                        data-testid="checkout-notes-input"
                        className="resize-none"
                    />
                </Field>

                <ConversionSummary
                    icon={ShoppingCart}
                    title="Resumen del pedido"
                    totals={drawerTotals}
                    className="shadow-sm"
                    sticky={false}
                >
                    <div className="grid gap-2 text-sm">
                        {items.map((item) => (
                            <div key={item.id} className="flex justify-between gap-3 text-muted-foreground">
                                <span className="min-w-0 truncate">{item.quantity}x {item.name}</span>
                                <span className="shrink-0">L. {calculateItemTotal(item.price, item.quantity, item.selectedModifiers).toFixed(2)}</span>
                            </div>
                        ))}
                    </div>
                </ConversionSummary>
                </div>
            </div>

            <div className="border-t bg-card p-4 text-card-foreground shadow-sm">
                <ConversionActionBar
                    type="button"
                    disabled={isSubmitting}
                    busy={isSubmitting}
                    busyLabel="Procesando..."
                    idleLabel="Confirmar pedido en WhatsApp"
                    icon={MessageCircle}
                    processingIcon={Loader2}
                    testId="checkout-submit"
                    onClick={handleSubmitOrder}
                >
                    Tu pedido se registrara y luego se abrira WhatsApp para confirmar.
                </ConversionActionBar>
            </div>
        </>
    );
}
