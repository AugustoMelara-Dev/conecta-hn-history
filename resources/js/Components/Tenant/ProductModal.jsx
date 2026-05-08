import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, CheckCircle2, ImageOff, Minus, Plus, ShoppingCart } from 'lucide-react';

import { Alert, AlertDescription } from '@/Components/ui/alert';
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
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Separator } from '@/Components/ui/separator';
import { Textarea } from '@/Components/ui/textarea';
import { Field, FieldDescription, FieldLabel } from '@/Components/ui/field';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';

function getModifierKey(groupId, optionId) {
    return `${groupId}:${optionId}`;
}

function isSingleModifierGroup(group) {
    return group.type === 'single' || Number(group.max_selections || 1) === 1;
}

function getModifierPrice(option) {
    return Number(option?.price ?? option?.price_adjustment ?? 0);
}

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
        if (!isOpen) {
            return;
        }

        setQuantity(1);
        setNotes('');

        if (!product?.modifier_groups) {
            setSelectedModifiers([]);
            return;
        }

        const defaults = [];
        product.modifier_groups.forEach((group) => {
            const defaultOption = group.options?.find((option) => option.is_default);

            if (defaultOption) {
                defaults.push({
                    groupId: group.id,
                    groupName: group.name,
                    optionId: defaultOption.id,
                    name: defaultOption.name,
                    price: getModifierPrice(defaultOption),
                    formattedPrice: defaultOption.formatted_price,
                });
            }
        });

        setSelectedModifiers(defaults);
    }, [isOpen, product]);

    const selectedMap = useMemo(() => {
        return new Set(selectedModifiers.map((modifier) => getModifierKey(modifier.groupId, modifier.optionId)));
    }, [selectedModifiers]);

    const selectedByGroup = useMemo(() => {
        return selectedModifiers.reduce((map, modifier) => {
            if (!map.has(modifier.groupId)) {
                map.set(modifier.groupId, []);
            }

            map.get(modifier.groupId).push(modifier);

            return map;
        }, new Map());
    }, [selectedModifiers]);

    const getOptionPayload = (group, option) => ({
        groupId: group.id,
        groupName: group.name,
        optionId: option.id,
        name: option.name,
        price: getModifierPrice(option),
        formattedPrice: option.formatted_price,
    });

    const handleModifierToggle = (group, option) => {
        setSelectedModifiers((current) => {
            if (isSingleModifierGroup(group)) {
                return [
                    ...current.filter((modifier) => modifier.groupId !== group.id),
                    getOptionPayload(group, option),
                ];
            }

            const exists = current.some(
                (modifier) => modifier.groupId === group.id && modifier.optionId === option.id
            );

            if (exists) {
                return current.filter(
                    (modifier) => !(modifier.groupId === group.id && modifier.optionId === option.id)
                );
            }

            const groupSelections = current.filter((modifier) => modifier.groupId === group.id);

            if (group.max_selections && groupSelections.length >= group.max_selections) {
                return current;
            }

            return [...current, getOptionPayload(group, option)];
        });
    };

    const requiredMissing = useMemo(() => {
        if (!product?.modifier_groups) {
            return [];
        }

        return product.modifier_groups.filter((group) => {
            return group.is_required && !selectedModifiers.some((modifier) => modifier.groupId === group.id);
        });
    }, [product?.modifier_groups, selectedModifiers]);

    const canAddToCart = requiredMissing.length === 0;

    const total = useMemo(() => {
        const basePrice = product?.price || 0;
        const modifiersTotal = selectedModifiers.reduce((sum, modifier) => sum + (modifier.price || 0), 0);

        return (basePrice + modifiersTotal) * quantity;
    }, [product?.price, quantity, selectedModifiers]);

    const handleConfirm = () => {
        if (!canAddToCart) {
            toast.warning('Selecciona las opciones requeridas', {
                description: requiredMissing.map((group) => group.name).join(', '),
            });
            return;
        }

        onConfirm(quantity, selectedModifiers, notes);
        toast.success('Producto agregado al carrito');
    };

    if (!product) {
        return null;
    }

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[92vh] overflow-hidden p-0 sm:max-w-2xl" data-testid="product-options-modal">
                <div className="grid max-h-[92vh] overflow-hidden">
                    <div className="relative aspect-[16/7] min-h-48 bg-muted">
                        {product.image ? (
                            <img
                                src={product.image}
                                alt={product.name}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <div className="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground">
                                <ImageOff className="size-10" aria-hidden="true" />
                                <p className="text-sm">Imagen no disponible</p>
                            </div>
                        )}
                    </div>

                    <div className="overflow-y-auto">
                        <DialogHeader className="p-6 pb-4">
                            <div className="flex flex-wrap items-start justify-between gap-3 pr-8">
                                <div>
                                    <DialogTitle>{product.name}</DialogTitle>
                                    <DialogDescription className="mt-2">
                                        {product.description || 'Personaliza este producto antes de agregarlo.'}
                                    </DialogDescription>
                                </div>
                                <Badge variant="secondary">
                                    {product.formatted_price || `L. ${Number(product.price || 0).toFixed(2)}`}
                                </Badge>
                            </div>
                        </DialogHeader>

                        <div className="flex flex-col gap-6 px-6 pb-6">
                            {requiredMissing.length > 0 && (
                                <Alert variant="warning">
                                    <AlertCircle className="mr-2 inline-block size-4" aria-hidden="true" />
                                    <AlertDescription className="inline">
                                        Selecciona las opciones requeridas antes de agregar el producto.
                                    </AlertDescription>
                                </Alert>
                            )}

                            {product.modifier_groups?.map((group) => {
                                const isSingleChoice = isSingleModifierGroup(group);
                                const selectedForGroup = selectedByGroup.get(group.id) || [];
                                const selectedValue = selectedForGroup[0]?.optionId == null
                                    ? undefined
                                    : String(selectedForGroup[0].optionId);

                                return (
                                    <section key={group.id} className="flex flex-col gap-3" aria-labelledby={`modifier-${group.id}`}>
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 id={`modifier-${group.id}`} className="text-sm font-semibold">
                                                        {group.name}
                                                    </h3>
                                                    {group.is_required && (
                                                        <Badge variant="secondary">Requerido</Badge>
                                                    )}
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {group.is_required ? 'Elige una opcion para continuar' : 'Opcional'}
                                                    {!isSingleChoice && group.max_selections
                                                        ? `, maximo ${group.max_selections}`
                                                        : ''}
                                                </p>
                                            </div>
                                        </div>

                                        {isSingleChoice ? (
                                            <RadioGroup
                                                value={selectedValue}
                                                onValueChange={(value) => {
                                                    const option = group.options?.find((item) => String(item.id) === value);

                                                    if (option) {
                                                        handleModifierToggle(group, option);
                                                    }
                                                }}
                                                aria-label={group.name}
                                                className="gap-2"
                                            >
                                                {group.options?.map((option) => (
                                                    <label
                                                        key={option.id}
                                                        className="flex cursor-pointer items-center justify-between gap-3 rounded-md border bg-card px-3 py-3 text-sm text-card-foreground hover:bg-accent hover:text-accent-foreground"
                                                    >
                                                        <span className="inline-flex items-center gap-2 text-left">
                                                            <RadioGroupItem value={String(option.id)} />
                                                            {option.name}
                                                        </span>
                                                        <span className="text-xs font-medium text-muted-foreground">
                                                            {option.formatted_price || `+L. ${getModifierPrice(option).toFixed(2)}`}
                                                        </span>
                                                    </label>
                                                ))}
                                            </RadioGroup>
                                        ) : (
                                            <div className="flex flex-col gap-2" role="group" aria-label={group.name}>
                                                {group.options?.map((option) => {
                                                    const selected = selectedMap.has(getModifierKey(group.id, option.id));
                                                    const maxReached = Boolean(
                                                        group.max_selections &&
                                                        selectedForGroup.length >= group.max_selections &&
                                                        !selected
                                                    );

                                                    return (
                                                        <Field
                                                            key={option.id}
                                                            data-disabled={maxReached}
                                                            className="rounded-md border bg-card px-3 py-3 data-[disabled=true]:opacity-50"
                                                        >
                                                            <label className="flex cursor-pointer items-center justify-between gap-3 text-sm">
                                                                <span className="inline-flex items-center gap-2 text-left">
                                                                    <Checkbox
                                                                        checked={selected}
                                                                        disabled={maxReached}
                                                                        onCheckedChange={() => handleModifierToggle(group, option)}
                                                                        aria-label={option.name}
                                                                    />
                                                                    {option.name}
                                                                </span>
                                                                <span className="inline-flex items-center gap-2 text-xs font-medium text-muted-foreground">
                                                                    {selected ? <CheckCircle2 className="size-3" aria-hidden="true" /> : null}
                                                                    {option.formatted_price || `+L. ${getModifierPrice(option).toFixed(2)}`}
                                                                </span>
                                                            </label>
                                                        </Field>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </section>
                                );
                            })}

                            <Field>
                                <FieldLabel htmlFor="product-notes">Notas especiales</FieldLabel>
                                <Textarea
                                    id="product-notes"
                                    value={notes}
                                    onChange={(event) => setNotes(event.target.value)}
                                    placeholder="Ej: sin cebolla, extra salsa..."
                                    rows={3}
                                />
                                <FieldDescription>
                                    Estas notas se envian junto con el producto en el pedido.
                                </FieldDescription>
                            </Field>
                        </div>
                    </div>

                    <Separator />

                    <DialogFooter className="gap-3 p-4 sm:items-center sm:justify-between">
                        <div className="flex items-center justify-between rounded-md border">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => setQuantity((current) => Math.max(1, current - 1))}
                                aria-label="Reducir cantidad"
                            >
                                <Minus data-icon="inline-start" aria-hidden="true" />
                            </Button>
                            <span className="min-w-10 text-center text-sm font-semibold">{quantity}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => setQuantity((current) => current + 1)}
                                aria-label="Aumentar cantidad"
                            >
                                <Plus data-icon="inline-start" aria-hidden="true" />
                            </Button>
                        </div>

                        <Button
                            type="button"
                            disabled={!canAddToCart}
                            onClick={handleConfirm}
                            className={cn('flex-1 sm:flex-none')}
                            data-testid="product-options-confirm"
                        >
                            <ShoppingCart data-icon="inline-start" aria-hidden="true" />
                            Agregar L. {total.toFixed(2)}
                        </Button>
                    </DialogFooter>
                </div>
            </DialogContent>
        </Dialog>
    );
}
