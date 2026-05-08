import React from 'react';
import { Minus, Plus, ShoppingBag, Trash2 } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { calculateItemTotal } from '@/Stores/useCartStore';

export default function CartItem({ item, onRemove, onUpdateQuantity }) {
    const itemTotal = calculateItemTotal(item.price, item.quantity, item.selectedModifiers);

    return (
        <Card data-testid="cart-item" className="overflow-hidden">
            <CardContent className="flex gap-3 p-3">
                <div className="size-16 flex-shrink-0 overflow-hidden rounded-md bg-muted">
                    {item.image ? (
                        <img
                            src={item.image}
                            alt={item.name}
                            className="size-full object-cover"
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center">
                            <ShoppingBag className="size-6 text-muted-foreground" aria-hidden="true" />
                        </div>
                    )}
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <h4 className="truncate text-sm font-semibold">{item.name}</h4>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={onRemove}
                            data-testid="cart-item-remove"
                            aria-label={`Eliminar ${item.name}`}
                            className="size-8 flex-shrink-0 text-muted-foreground"
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
                        </Button>
                    </div>

                    {item.selectedModifiers?.length > 0 && (
                        <div className="mt-1 flex flex-wrap gap-1">
                            {item.selectedModifiers.map((mod, idx) => (
                                <Badge key={`${mod.name}-${idx}`} variant="secondary" className="font-normal">
                                    + {mod.name}
                                    {mod.price > 0 ? ` (+L.${mod.price.toFixed(2)})` : ''}
                                </Badge>
                            ))}
                        </div>
                    )}

                    {item.notes && (
                        <p className="mt-1 truncate text-xs text-muted-foreground">
                            Nota: {item.notes}
                        </p>
                    )}

                    <div className="mt-3 flex items-center justify-between gap-3">
                        <span className="text-sm font-bold text-primary">
                            L. {itemTotal.toFixed(2)}
                        </span>

                        <div className="flex items-center gap-1 rounded-md border bg-background p-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => onUpdateQuantity(item.quantity - 1)}
                                data-testid="cart-item-decrement"
                                aria-label={`Reducir cantidad de ${item.name}`}
                                className="size-7"
                            >
                                <Minus className="size-3.5" aria-hidden="true" />
                            </Button>
                            <span className="min-w-6 text-center text-sm font-medium" aria-live="polite">
                                {item.quantity}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => onUpdateQuantity(item.quantity + 1)}
                                data-testid="cart-item-increment"
                                aria-label={`Aumentar cantidad de ${item.name}`}
                                className="size-7"
                            >
                                <Plus className="size-3.5" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
