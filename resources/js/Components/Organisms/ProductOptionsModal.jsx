import { useState, useCallback } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { ShoppingBag, Plus, Minus, Check } from 'lucide-react';
import useCartStore from '@/Stores/useCartStore';
import { cn } from '@/lib/utils';

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * PRODUCT OPTIONS MODAL - Dovra AI
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Modal para seleccionar opciones/variantes antes de agregar al carrito.
 * Maneja grupos de selección única (radio) y múltiple (checkbox).
 */
export default function ProductOptionsModal({
    product,
    isOpen,
    onClose,
    tenantName,
    primaryColor = '#000000'
}) {
    const addItem = useCartStore(state => state.addItem);

    // Estado local para opciones seleccionadas por grupo
    const [selectedOptions, setSelectedOptions] = useState({});
    const [quantity, setQuantity] = useState(1);
    const [notes, setNotes] = useState('');

    // Grupos de opciones del producto
    const optionGroups = product?.option_groups || product?.optionGroups || [];
    const hasOptions = optionGroups.length > 0;

    // Resetear estado cuando se abre el modal
    const handleOpenChange = (open) => {
        if (!open) {
            setSelectedOptions({});
            setQuantity(1);
            setNotes('');
            onClose();
        }
    };

    // Manejar selección de opción
    const handleSelectOption = (group, option) => {
        if (group.type === 'single') {
            // Radio - solo una opción
            setSelectedOptions(prev => ({
                ...prev,
                [group.id]: [option]
            }));
        } else {
            // Checkbox - múltiples opciones
            setSelectedOptions(prev => {
                const current = prev[group.id] || [];
                const exists = current.find(o => o.id === option.id);

                if (exists) {
                    // Quitar opción
                    return {
                        ...prev,
                        [group.id]: current.filter(o => o.id !== option.id)
                    };
                } else {
                    // Agregar opción (verificar máximo)
                    if (group.max_selections && current.length >= group.max_selections) {
                        return prev; // Ya alcanzó el máximo
                    }
                    return {
                        ...prev,
                        [group.id]: [...current, option]
                    };
                }
            });
        }
    };

    // Verificar si una opción está seleccionada
    const isOptionSelected = (groupId, optionId) => {
        const groupOptions = selectedOptions[groupId] || [];
        return groupOptions.some(o => o.id === optionId);
    };

    // Verificar si cumple con las reglas de validación
    const isValid = () => {
        for (const group of optionGroups) {
            const selected = selectedOptions[group.id] || [];

            if (group.is_required && selected.length === 0) {
                return false;
            }

            if (group.min_selections && selected.length < group.min_selections) {
                return false;
            }
        }
        return true;
    };

    // Calcular precio total con modificadores
    const calculateTotalPrice = () => {
        const basePrice = parseFloat(product?.price) || 0;

        let modifiersTotal = 0;
        Object.values(selectedOptions).forEach(options => {
            options.forEach(opt => {
                modifiersTotal += parseFloat(opt.price_modifier) || 0;
            });
        });

        return (basePrice + modifiersTotal) * quantity;
    };

    // Agregar al carrito
    const handleAddToCart = () => {
        if (!isValid()) return;

        // Aplanar todas las opciones seleccionadas
        const flatOptions = Object.entries(selectedOptions).flatMap(([groupId, options]) => {
            const group = optionGroups.find(g => g.id === parseInt(groupId));
            return options.map(opt => ({
                ...opt,
                groupName: group?.name || ''
            }));
        });

        addItem(product, flatOptions, quantity, notes);
        handleOpenChange(false);
    };

    // Agregar directamente si no tiene opciones
    const handleQuickAdd = () => {
        addItem(product, [], 1, '');
        onClose();
    };

    if (!product) return null;

    // Si no tiene opciones, usar quick add
    if (!hasOptions) {
        return (
            <Dialog open={isOpen} onOpenChange={handleOpenChange}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{product.name}</DialogTitle>
                        <DialogDescription>{tenantName}</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {/* Imagen */}
                        <div className="aspect-video bg-muted rounded-lg overflow-hidden">
                            {product.image_path ? (
                                <img
                                    src={`/storage/${product.image_path}`}
                                    alt={product.name}
                                    className="w-full h-full object-cover"
                                />
                            ) : (
                                <div className="w-full h-full flex items-center justify-center">
                                    <ShoppingBag className="w-12 h-12 text-muted-foreground/20" />
                                </div>
                            )}
                        </div>

                        {/* Descripción */}
                        <p className="text-muted-foreground">{product.description}</p>

                        {/* Precio y botón */}
                        <div className="flex items-center justify-between pt-4 border-t">
                            <span className="text-2xl font-bold">L. {product.price}</span>
                            <Button
                                onClick={handleQuickAdd}
                                className="rounded-full"
                                style={{ backgroundColor: primaryColor }}
                            >
                                <Plus className="w-4 h-4 mr-2" />
                                Agregar al Carrito
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        );
    }

    // Modal completo con opciones
    return (
        <Dialog open={isOpen} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{product.name}</DialogTitle>
                    <DialogDescription>Personaliza tu pedido</DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Imagen */}
                    <div className="aspect-video bg-muted rounded-lg overflow-hidden">
                        {product.image_path ? (
                            <img
                                src={`/storage/${product.image_path}`}
                                alt={product.name}
                                className="w-full h-full object-cover"
                            />
                        ) : (
                            <div className="w-full h-full flex items-center justify-center">
                                <ShoppingBag className="w-12 h-12 text-muted-foreground/20" />
                            </div>
                        )}
                    </div>

                    {/* Grupos de Opciones */}
                    {optionGroups.map(group => (
                        <div key={group.id} className="space-y-3">
                            <div className="flex items-center gap-2">
                                <h4 className="font-semibold">{group.name}</h4>
                                {group.is_required && (
                                    <Badge variant="destructive" className="text-xs">Requerido</Badge>
                                )}
                                {group.type === 'multiple' && group.max_selections && (
                                    <Badge variant="secondary" className="text-xs">
                                        Máx. {group.max_selections}
                                    </Badge>
                                )}
                            </div>

                            <div className="grid gap-2">
                                {(group.options || []).map(option => {
                                    const isSelected = isOptionSelected(group.id, option.id);

                                    return (
                                        <button
                                            key={option.id}
                                            onClick={() => handleSelectOption(group, option)}
                                            className={cn(
                                                "flex items-center justify-between p-3 rounded-lg border transition-all",
                                                isSelected
                                                    ? "border-2 bg-accent/10"
                                                    : "border-border hover:border-primary/50 hover:bg-accent/5"
                                            )}
                                            style={isSelected ? { borderColor: primaryColor } : {}}
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className={cn(
                                                    "w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all",
                                                    isSelected ? "bg-primary border-primary" : "border-muted-foreground/30"
                                                )}
                                                    style={isSelected ? { backgroundColor: primaryColor, borderColor: primaryColor } : {}}
                                                >
                                                    {isSelected && <Check className="w-3 h-3 text-white" />}
                                                </div>
                                                <span className="font-medium">{option.name}</span>
                                            </div>

                                            {option.price_modifier !== 0 && (
                                                <span className={cn(
                                                    "text-sm font-medium",
                                                    option.price_modifier > 0 ? "text-emerald-600" : "text-rose-600"
                                                )}>
                                                    {option.price_modifier > 0 ? '+' : ''}L. {option.price_modifier}
                                                </span>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ))}

                    {/* Notas especiales */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Notas especiales (opcional)</label>
                        <textarea
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            placeholder="Ej: Sin cebolla, extra salsa..."
                            className="w-full p-3 border rounded-lg text-sm resize-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            rows={2}
                        />
                    </div>

                    {/* Cantidad y Total */}
                    <div className="flex items-center justify-between pt-4 border-t">
                        <div className="flex items-center gap-3 bg-muted rounded-full p-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 rounded-full"
                                onClick={() => setQuantity(q => Math.max(1, q - 1))}
                            >
                                <Minus className="w-4 h-4" />
                            </Button>
                            <span className="w-8 text-center font-bold">{quantity}</span>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 rounded-full"
                                onClick={() => setQuantity(q => q + 1)}
                            >
                                <Plus className="w-4 h-4" />
                            </Button>
                        </div>

                        <Button
                            onClick={handleAddToCart}
                            disabled={!isValid()}
                            className="rounded-full px-6"
                            style={{ backgroundColor: primaryColor }}
                        >
                            Agregar • L. {calculateTotalPrice().toFixed(2)}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
