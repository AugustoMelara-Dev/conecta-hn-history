import React from 'react';
import { Tag, X } from 'lucide-react';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Field, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Spinner } from '@/Components/ui/spinner';

export default function CouponSection({
    couponCode,
    showCouponInput,
    setShowCouponInput,
    couponInput,
    setCouponInput,
    isApplyingCoupon,
    applyCoupon,
    removeCoupon,
    couponError
}) {
    const couponErrorId = couponError ? 'coupon-code-error' : undefined;

    if (couponCode) {
        return (
            <div
                data-testid="coupon-applied"
                className="flex items-center justify-between rounded-md border border-primary/25 bg-primary/10 px-3 py-2"
            >
                <div className="flex items-center gap-2">
                    <Tag className="size-4 text-primary" aria-hidden="true" />
                    <Badge variant="secondary">{couponCode}</Badge>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={() => {
                        removeCoupon();
                        setShowCouponInput(true);
                    }}
                    data-testid="coupon-remove"
                    aria-label="Eliminar cupon"
                    className="size-8 text-muted-foreground"
                >
                    <X className="size-4" aria-hidden="true" />
                </Button>
            </div>
        );
    }

    if (!showCouponInput) {
        return (
            <Button
                type="button"
                variant="link"
                size="sm"
                onClick={() => setShowCouponInput(true)}
                data-testid="coupon-reveal"
                className="h-auto justify-start px-0 text-primary"
            >
                <Tag className="mr-1.5 size-3.5" aria-hidden="true" />
                Tienes un codigo de descuento?
            </Button>
        );
    }

    return (
        <Field>
            <FieldLabel htmlFor="coupon-code">Codigo de descuento</FieldLabel>
            <div className="flex gap-2">
                <Input
                    id="coupon-code"
                    type="text"
                    value={couponInput}
                    onChange={(e) => setCouponInput(e.target.value.toUpperCase())}
                    placeholder="Ingresa tu codigo"
                    autoFocus
                    aria-invalid={!!couponError}
                    aria-describedby={couponErrorId}
                    data-testid="coupon-input"
                />
                <Button
                    type="button"
                    onClick={async () => {
                        const result = await applyCoupon(couponInput);
                        if (result.success) {
                            setCouponInput('');
                            setShowCouponInput(false);
                        }
                    }}
                    disabled={isApplyingCoupon || !couponInput}
                    data-testid="coupon-apply"
                    className="min-w-24"
                >
                    {isApplyingCoupon ? (
                        <Spinner />
                    ) : (
                        'Aplicar'
                    )}
                </Button>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => {
                    setShowCouponInput(false);
                    setCouponInput('');
                }}
                data-testid="coupon-cancel"
                className="w-fit px-0 text-muted-foreground"
            >
                Cancelar
            </Button>

            {couponError && (
                <Alert id={couponErrorId} variant="destructive" aria-live="polite">
                    <AlertDescription>{couponError}</AlertDescription>
                </Alert>
            )}
        </Field>
    );
}
