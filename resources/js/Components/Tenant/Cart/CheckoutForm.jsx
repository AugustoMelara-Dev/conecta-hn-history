import React from 'react';
import { AlertCircle, MapPin, MessageCircle, Package, Phone, Truck, User } from 'lucide-react';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Separator } from '@/Components/ui/separator';
import { Spinner } from '@/Components/ui/spinner';
import { Textarea } from '@/Components/ui/textarea';
import { cn } from '@/lib/utils';
import { calculateItemTotal } from '@/Stores/useCartStore';

function FieldMessage({ id, children }) {
    if (!children) {
        return null;
    }

    return (
        <p id={id} className="text-sm text-destructive" aria-live="polite">
            {children}
        </p>
    );
}

export default function CheckoutForm({
    customerName, setCustomerName,
    customerPhone, setCustomerPhone,
    deliveryType, setDeliveryType,
    customerAddress, setCustomerAddress,
    customerNotes, setCustomerNotes,
    items,
    grandTotal,
    handleSubmitOrder,
    isSubmitting,
    submitError,
    errors
}) {
    const nameErrorId = errors.customerName ? 'checkout-name-error' : undefined;
    const phoneErrorId = errors.customerPhone ? 'checkout-phone-error' : undefined;
    const addressErrorId = errors.customerAddress ? 'checkout-address-error' : undefined;

    return (
        <form
            data-testid="checkout-form"
            className="flex min-h-0 flex-1 flex-col"
            onSubmit={(event) => {
                event.preventDefault();
                handleSubmitOrder();
            }}
        >
            <div className="flex-1 overflow-y-auto p-4">
                <FieldGroup className="gap-5">
                    {submitError && (
                        <Alert variant="destructive" aria-live="polite">
                            <div className="flex items-start gap-3">
                                <AlertCircle className="mt-0.5 size-5 flex-shrink-0" aria-hidden="true" />
                                <AlertDescription>{submitError}</AlertDescription>
                            </div>
                        </Alert>
                    )}

                    <Field>
                        <FieldLabel htmlFor="checkout-name" className="flex items-center gap-2">
                            <User className="size-4" aria-hidden="true" />
                            Tu nombre *
                        </FieldLabel>
                        <Input
                            id="checkout-name"
                            type="text"
                            value={customerName}
                            onChange={(e) => setCustomerName(e.target.value)}
                            placeholder="Ej: Maria Garcia"
                            aria-invalid={!!errors.customerName}
                            aria-describedby={nameErrorId}
                            data-testid="checkout-name"
                            className={cn(errors.customerName && 'border-destructive focus-visible:ring-destructive')}
                        />
                        <FieldMessage id={nameErrorId}>{errors.customerName}</FieldMessage>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="checkout-phone" className="flex items-center gap-2">
                            <Phone className="size-4" aria-hidden="true" />
                            Tu telefono *
                        </FieldLabel>
                        <Input
                            id="checkout-phone"
                            type="tel"
                            value={customerPhone}
                            onChange={(e) => setCustomerPhone(e.target.value)}
                            placeholder="Ej: 9999-1234"
                            aria-invalid={!!errors.customerPhone}
                            aria-describedby={phoneErrorId}
                            data-testid="checkout-phone"
                            className={cn(errors.customerPhone && 'border-destructive focus-visible:ring-destructive')}
                        />
                        <FieldMessage id={phoneErrorId}>{errors.customerPhone}</FieldMessage>
                    </Field>

                    <Field>
                        <FieldLabel id="delivery-type-label">Como recibiras tu pedido?</FieldLabel>
                        <RadioGroup
                            value={deliveryType}
                            onValueChange={setDeliveryType}
                            aria-labelledby="delivery-type-label"
                            className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                        >
                            <label
                                htmlFor="checkout-delivery-pickup"
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-lg border p-3',
                                    deliveryType === 'pickup' && 'border-primary bg-primary/10'
                                )}
                            >
                                <RadioGroupItem
                                    id="checkout-delivery-pickup"
                                    value="pickup"
                                    data-testid="checkout-delivery-pickup"
                                    className="mt-1"
                                />
                                <span className="flex flex-col gap-1">
                                    <span className="flex items-center gap-2 text-sm font-medium">
                                        <Package className="size-4" aria-hidden="true" />
                                        Recoger en tienda
                                    </span>
                                    <span className="text-xs text-muted-foreground">Pasas por tu pedido cuando este listo.</span>
                                </span>
                            </label>

                            <label
                                htmlFor="checkout-delivery-delivery"
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-lg border p-3',
                                    deliveryType === 'delivery' && 'border-primary bg-primary/10'
                                )}
                            >
                                <RadioGroupItem
                                    id="checkout-delivery-delivery"
                                    value="delivery"
                                    data-testid="checkout-delivery-delivery"
                                    className="mt-1"
                                />
                                <span className="flex flex-col gap-1">
                                    <span className="flex items-center gap-2 text-sm font-medium">
                                        <Truck className="size-4" aria-hidden="true" />
                                        A domicilio
                                    </span>
                                    <span className="text-xs text-muted-foreground">El negocio confirma cobertura y envio.</span>
                                </span>
                            </label>
                        </RadioGroup>
                    </Field>

                    {deliveryType === 'delivery' && (
                        <Field>
                            <FieldLabel htmlFor="checkout-address" className="flex items-center gap-2">
                                <MapPin className="size-4" aria-hidden="true" />
                                Direccion de entrega *
                            </FieldLabel>
                            <Textarea
                                id="checkout-address"
                                value={customerAddress}
                                onChange={(e) => setCustomerAddress(e.target.value)}
                                placeholder="Colonia, calle, numero de casa, referencias..."
                                rows={3}
                                aria-invalid={!!errors.customerAddress}
                                aria-describedby={addressErrorId}
                                data-testid="checkout-address"
                                className={cn('resize-none', errors.customerAddress && 'border-destructive focus-visible:ring-destructive')}
                            />
                            <FieldMessage id={addressErrorId}>{errors.customerAddress}</FieldMessage>
                        </Field>
                    )}

                    <Field>
                        <FieldLabel htmlFor="checkout-notes">Notas adicionales (opcional)</FieldLabel>
                        <Textarea
                            id="checkout-notes"
                            value={customerNotes}
                            onChange={(e) => setCustomerNotes(e.target.value)}
                            placeholder="Instrucciones especiales, alergenos, etc."
                            rows={2}
                            data-testid="checkout-notes"
                            className="resize-none"
                        />
                    </Field>

                    <Card>
                        <CardHeader className="p-4 pb-2">
                            <CardTitle className="text-base">Resumen del pedido</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2 p-4 pt-0 text-sm">
                            {items.map(item => (
                                <div key={item.id} className="flex justify-between gap-3">
                                    <span className="min-w-0 truncate text-muted-foreground">
                                        {item.quantity}x {item.name}
                                    </span>
                                    <span className="font-medium">
                                        L. {calculateItemTotal(item.price, item.quantity, item.selectedModifiers).toFixed(2)}
                                    </span>
                                </div>
                            ))}
                            <Separator className="my-1" />
                            <div className="flex justify-between font-bold">
                                <span>Total</span>
                                <span>L. {grandTotal.toFixed(2)}</span>
                            </div>
                        </CardContent>
                    </Card>
                </FieldGroup>
            </div>

            <div className="border-t bg-background/95 p-4">
                <Button
                    type="submit"
                    disabled={isSubmitting}
                    data-testid="checkout-submit"
                    className="h-12 w-full"
                >
                    {isSubmitting ? (
                        <>
                            <Spinner className="mr-2 size-5" />
                            Procesando...
                        </>
                    ) : (
                        <>
                            <MessageCircle className="mr-2 size-5" aria-hidden="true" />
                            Confirmar pedido en WhatsApp
                        </>
                    )}
                </Button>

                <p className="mt-3 text-center text-xs text-muted-foreground">
                    Tu pedido sera registrado y se abrira WhatsApp para confirmar.
                </p>
            </div>
        </form>
    );
}
