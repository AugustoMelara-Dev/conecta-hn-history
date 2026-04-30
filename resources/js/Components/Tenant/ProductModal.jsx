/**
 * ProductModal - Modifier Selection Modal for Pro Tier.
 */

import { useEffect, useState } from 'react';
import { Minus, Plus, ShoppingCart } from 'lucide-react';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Textarea } from '@/Components/ui/textarea';
import { cn } from '@/lib/utils';

export default function ProductModal({
    isOpen,
    onClose,
    product,
    onConfirm,
}) {
    const [quantity, setQuantity] = useState(1);
    const [selectedModifiers, setSelectedModifiers] = useState([]);
    const [notes, setNotes] = useState('');

    useEffect(() => {
        if (isOpen) {
            setQuantity(1);
            setSelectedModifiers([]);
            setNotes('');

            if (product?.modifier_groups) {
                const defaults = [];
                product.modifier_groups.forEach(group => {
                    const defaultOption = group.options?.find(opt => opt.is_default);
                    if (defaultOption) {
                        defaults.push({
                            groupId: group.id,
                            groupName: group.name,
                            optionId: defaultOption.id,
                            name: defaultOption.name,
                            price: defaultOption.price,
                        });
                    }
                });
                setSelectedModifiers(defaults);
            }
        }
    }, [isOpen, product]);

    const handleModifierToggle = (group, option) => {
        setSelectedModifiers(prev => {
            if (group.type === 'single') {
                return [
                    ...prev.filter(modifier => modifier.groupId !== group.id),
                    {
                        groupId: group.id,
                        groupName: group.name,
                        optionId: option.id,
                        name: option.name,
                        price: option.price,
                    },
                ];
            }

            const exists = prev.find(
                modifier => modifier.groupId === group.id && modifier.optionId === option.id
            );

            if (exists) {
                return prev.filter(
                    modifier => !(modifier.groupId === group.id && modifier.optionId === option.id)
                );
            }

            const groupSelections = prev.filter(modifier => modifier.groupId === group.id);
            if (group.max_selections && groupSelections.length >= group.max_selections) {
                return prev;
            }

            return [
                ...prev,
                {
                    groupId: group.id,
                    groupName: group.name,
                    optionId: option.id,
                    name: option.name,
                    price: option.price,
                },
            ];
        });
    };

    const isOptionSelected = (groupId, optionId) => {
        return selectedModifiers.some(
            modifier => modifier.groupId === groupId && modifier.optionId === optionId
        );
    };

    const getSelectedOptionId = groupId => {
        return String(selectedModifiers.find(modifier => modifier.groupId === groupId)?.optionId ?? '');
    };

    const calculateTotal = () => {
        const basePrice = product?.price || 0;
        const modifiersTotal = selectedModifiers.reduce(
            (sum, modifier) => sum + (modifier.price || 0),
            0
        );
        return (basePrice + modifiersTotal) * quantity;
    };

    const handleConfirm = () => {
        onConfirm(quantity, selectedModifiers, notes);
    };

    const isValid = () => {
        if (!product?.modifier_groups) return true;

        return product.modifier_groups.every(group => {
            if (!group.is_required) return true;
            return selectedModifiers.some(modifier => modifier.groupId === group.id);
        });
    };

    if (!product) return null;

    const hasModifiers = Boolean(product.modifier_groups?.length);

    return (
        <Dialog
            open={isOpen}
            onOpenChange={open => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="max-h-[92vh] overflow-hidden p-0 sm:max-w-lg">
                <div className="relative h-48 overflow-hidden bg-muted">
                    {product.image ? (
                        <img
                            src={product.image}
                            alt={product.name}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full items-center justify-center text-muted-foreground">
                            <ShoppingCart className="size-10" aria-hidden="true" />
                        </div>
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-background via-background/70 to-transparent" />
                </div>

                <DialogHeader className="px-5 pt-5 text-left">
                    <div className="flex items-start justify-between gap-3 pr-8">
                        <div className="grid gap-2">
                            <DialogTitle className="text-xl">{product.name}</DialogTitle>
                            <DialogDescription>
                                {product.description || 'Personaliza este producto antes de agregarlo al carrito.'}
                            </DialogDescription>
                        </div>
                        <Badge variant="secondary" className="shrink-0">
                            {product.formatted_price}
                        </Badge>
                    </div>
                </DialogHeader>

                <div className="max-h-[42vh] overflow-y-auto px-5 py-4">
                    <FieldGroup className="gap-5">
                        {hasModifiers ? (
                            product.modifier_groups.map(group => (
                                <Field
                                    key={group.id}
                                    aria-required={group.is_required ? 'true' : undefined}
                                    className="rounded-lg border bg-card p-3"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="grid gap-1">
                                            <FieldLabel asChild>
                                                <span>{group.name}</span>
                                            </FieldLabel>
                                            <FieldDescription>
                                                {group.type === 'multiple' && group.max_selections
                                                    ? `Elige hasta ${group.max_selections} opciones.`
                                                    : 'Elige una opción.'}
                                            </FieldDescription>
                                        </div>
                                        {group.is_required ? (
                                            <Badge variant="outline">Requerido</Badge>
                                        ) : null}
                                    </div>

                                    {group.type === 'single' ? (
                                        <RadioGroup
                                            value={getSelectedOptionId(group.id)}
                                            onValueChange={value => {
                                                const option = group.options?.find(
                                                    item => String(item.id) === value
                                                );
                                                if (option) {
                                                    handleModifierToggle(group, option);
                                                }
                                            }}
                                        >
                                            {group.options?.map(option => {
                                                const optionId = `modifier-${group.id}-${option.id}`;

                                                return (
                                                    <label
                                                        key={option.id}
                                                        htmlFor={optionId}
                                                        className={cn(
                                                            'flex cursor-pointer items-center justify-between gap-3 rounded-md border p-3 text-sm',
                                                            isOptionSelected(group.id, option.id)
                                                                ? 'border-primary bg-primary/5'
                                                                : 'border-border bg-background hover:bg-muted'
                                                        )}
                                                    >
                                                        <span className="flex min-w-0 items-center gap-3">
                                                            <RadioGroupItem
                                                                id={optionId}
                                                                value={String(option.id)}
                                                            />
                                                            <span className="truncate font-medium">{option.name}</span>
                                                        </span>
                                                        <span className="shrink-0 text-muted-foreground">
                                                            {option.formatted_price}
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </RadioGroup>
                                    ) : (
                                        <div className="grid gap-2">
                                            {group.options?.map(option => {
                                                const selected = isOptionSelected(group.id, option.id);
                                                const groupSelections = selectedModifiers.filter(
                                                    modifier => modifier.groupId === group.id
                                                );
                                                const maxReached = Boolean(
                                                    group.max_selections &&
                                                        groupSelections.length >= group.max_selections
                                                );
                                                const disabled = !selected && maxReached;
                                                const optionId = `modifier-${group.id}-${option.id}`;

                                                return (
                                                    <label
                                                        key={option.id}
                                                        htmlFor={optionId}
                                                        className={cn(
                                                            'flex items-center justify-between gap-3 rounded-md border p-3 text-sm',
                                                            disabled
                                                                ? 'cursor-not-allowed opacity-60'
                                                                : 'cursor-pointer hover:bg-muted',
                                                            selected
                                                                ? 'border-primary bg-primary/5'
                                                                : 'border-border bg-background'
                                                        )}
                                                    >
                                                        <span className="flex min-w-0 items-center gap-3">
                                                            <Checkbox
                                                                id={optionId}
                                                                checked={selected}
                                                                disabled={disabled}
                                                                onCheckedChange={() => handleModifierToggle(group, option)}
                                                            />
                                                            <span className="truncate font-medium">{option.name}</span>
                                                        </span>
                                                        <span className="shrink-0 text-muted-foreground">
                                                            {option.formatted_price}
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    )}
                                </Field>
                            ))
                        ) : (
                            <Field className="rounded-lg border bg-card p-3">
                                <FieldLabel>Sin modificadores</FieldLabel>
                                <FieldDescription>
                                    Este producto se agregará al carrito sin opciones adicionales.
                                </FieldDescription>
                            </Field>
                        )}

                        <Field>
                            <FieldLabel htmlFor={`product-notes-${product.id}`}>
                                Notas especiales
                            </FieldLabel>
                            <Textarea
                                id={`product-notes-${product.id}`}
                                name="product_notes"
                                value={notes}
                                onChange={event => setNotes(event.target.value)}
                                placeholder="Ej: sin cebolla, extra salsa..."
                                rows={3}
                            />
                            <FieldDescription>
                                Opcional. El negocio verá esta nota junto al pedido.
                            </FieldDescription>
                        </Field>
                    </FieldGroup>
                </div>

                <DialogFooter className="border-t bg-muted/40 px-5 py-4 sm:justify-between">
                    <div className="flex w-full items-center gap-3">
                        <div className="flex items-center rounded-md border bg-background">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Restar una unidad"
                                onClick={() => setQuantity(current => Math.max(1, current - 1))}
                            >
                                <Minus className="size-4" aria-hidden="true" />
                            </Button>
                            <span
                                className="min-w-10 text-center text-sm font-semibold"
                                aria-live="polite"
                                aria-label={`${quantity} unidades`}
                            >
                                {quantity}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Sumar una unidad"
                                onClick={() => setQuantity(current => current + 1)}
                            >
                                <Plus className="size-4" aria-hidden="true" />
                            </Button>
                        </div>

                        <Button
                            type="button"
                            onClick={handleConfirm}
                            disabled={!isValid()}
                            className="min-w-0 flex-1"
                        >
                            <ShoppingCart className="size-4" aria-hidden="true" />
                            <span className="truncate">Agregar L. {calculateTotal().toFixed(2)}</span>
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
