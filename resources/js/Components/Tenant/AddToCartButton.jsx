import { useState } from 'react';
import { Check, Plus, ShoppingCart, Zap } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import ProductModal from './ProductModal';
import useCartStore from '@/Stores/useCartStore';
import { cn } from '@/lib/utils';

export default function AddToCartButton({
    product,
    tenantId,
    tenantWhatsapp,
    tenantName,
    tenantSlug,
    variant = 'default',
    className,
    onOpenCart,
}) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [showAdded, setShowAdded] = useState(false);

    const addItem = useCartStore((state) => state.addItem);
    const initForTenant = useCartStore((state) => state.initForTenant);

    const showAddedFeedback = () => {
        setShowAdded(true);
        window.setTimeout(() => setShowAdded(false), 1500);
    };

    const handleQuickAdd = () => {
        addItem(product, 1, [], '');
        showAddedFeedback();

        if (variant === 'buy-now' && onOpenCart) {
            window.setTimeout(() => onOpenCart(), 300);
        }
    };

    const handleOpenModal = () => {
        initForTenant(tenantSlug, tenantWhatsapp, tenantName, tenantId);

        if (product.has_modifiers && product.modifier_groups?.length > 0) {
            setIsModalOpen(true);
            return;
        }

        handleQuickAdd();
    };

    const handleAddFromModal = (quantity, selectedModifiers, notes) => {
        addItem(product, quantity, selectedModifiers, notes);
        setIsModalOpen(false);
        showAddedFeedback();

        if (variant === 'buy-now' && onOpenCart) {
            window.setTimeout(() => onOpenCart(), 300);
        }
    };

    const isIconOnly = variant === 'icon-only';
    const buttonSize = isIconOnly ? 'icon' : variant === 'compact' ? 'sm' : 'default';
    const buttonVariant = variant === 'buy-now' || showAdded ? 'default' : 'default';

    const content = showAdded ? (
        <>
            <Check data-icon={isIconOnly ? undefined : 'inline-start'} aria-hidden="true" />
            {!isIconOnly && 'Agregado'}
        </>
    ) : variant === 'buy-now' ? (
        <>
            <Zap data-icon="inline-start" aria-hidden="true" />
            Comprar ahora
        </>
    ) : isIconOnly ? (
        <Plus aria-hidden="true" />
    ) : (
        <>
            <ShoppingCart data-icon="inline-start" aria-hidden="true" />
            Agregar
        </>
    );

    return (
        <>
            <Button
                type="button"
                variant={buttonVariant}
                size={buttonSize}
                onClick={handleOpenModal}
                className={cn(
                    'bg-[var(--tenant-primary,var(--primary))] text-[var(--tenant-primary-foreground,var(--primary-foreground))] hover:brightness-95',
                    className
                )}
                disabled={showAdded}
                aria-label={isIconOnly ? `Agregar ${product?.name || 'producto'}` : undefined}
                data-testid="microsite-add-to-cart"
            >
                {content}
            </Button>

            <ProductModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                product={product}
                onConfirm={handleAddFromModal}
            />
        </>
    );
}
