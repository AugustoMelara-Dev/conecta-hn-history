import React from 'react';
import { ShoppingBag, Minus, Plus, Trash2 } from 'lucide-react';
import { calculateItemTotal } from '@/Stores/useCartStore';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';

export default function CartItem({ item, onRemove, onUpdateQuantity }) {
    const itemTotal = calculateItemTotal(item.price, item.quantity, item.selectedModifiers);

    return (
        <Card>
            <CardContent className="flex gap-3 p-3">
            <div className="h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg bg-muted">
                {item.image ? (
                    <img
                        src={item.image}
                        alt={item.name}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <ShoppingBag className="h-6 w-6 text-muted-foreground" data-icon aria-hidden="true" />
                    </div>
                )}
            </div>

            <div className="min-w-0 flex-1">
                <h4 className="truncate font-semibold text-foreground">{item.name}</h4>

                {item.selectedModifiers?.length > 0 ? (
                    <div className="mt-1 grid gap-0.5">
                        {item.selectedModifiers.map((modifier, index) => (
                            <p key={index} className="text-xs text-muted-foreground">
                                + {modifier.name}
                                {modifier.price > 0 ? (
                                    <span className="ml-1 text-foreground">
                                        (+L.{modifier.price.toFixed(2)})
                                    </span>
                                ) : null}
                            </p>
                        ))}
                    </div>
                ) : null}

                {item.notes ? (
                    <p className="mt-1 truncate text-xs text-muted-foreground">
                        Notas: {item.notes}
                    </p>
                ) : null}

                <div className="mt-2 flex items-center justify-between">
                    <span className="text-sm font-bold text-foreground">
                        L. {itemTotal.toFixed(2)}
                    </span>

                    <div className="flex items-center gap-1 rounded-lg bg-muted">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => onUpdateQuantity(item.quantity - 1)}
                            className="h-7 w-7"
                            aria-label="Reducir cantidad"
                        >
                            <Minus className="h-3 w-3" data-icon aria-hidden="true" />
                        </Button>
                        <span className="w-5 text-center text-sm font-medium text-foreground" aria-live="polite">
                            {item.quantity}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => onUpdateQuantity(item.quantity + 1)}
                            className="h-7 w-7"
                            aria-label="Aumentar cantidad"
                        >
                            <Plus className="h-3 w-3" data-icon aria-hidden="true" />
                        </Button>
                    </div>
                </div>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onRemove}
                className="h-8 w-8 flex-shrink-0"
                aria-label="Eliminar producto"
            >
                <Trash2 className="h-4 w-4" data-icon aria-hidden="true" />
            </Button>
            </CardContent>
        </Card>
    );
}
