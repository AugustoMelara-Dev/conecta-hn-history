/**
 * AddToCartButton - Stateful Component for Pro Tier
 * 
 * Purpose: Opens ProductModal for modifier selection before adding to cart.
 * Connected to Zustand store for cart management.
 * 
 * ENHANCED: Now supports "Buy Now" flow that adds to cart and immediately
 * opens the checkout modal for quick purchases.
 * 
 * @usage Only rendered for tenants with plan='pro' or 'enterprise'
 */

import { useState } from 'react';
import { ShoppingCart, Plus, Check, Zap } from 'lucide-react';
import { cn } from '@/lib/utils';
import ProductModal from './ProductModal';
import useCartStore from '@/Stores/useCartStore';

export default function AddToCartButton({
    product,
    tenantId,           // NEW: Required for order submission
    tenantWhatsapp,
    tenantName,
    tenantSlug,
    variant = 'default', // 'default' | 'compact' | 'icon-only' | 'buy-now'
    className,
    onOpenCart,         // NEW: Callback to open FloatingCart (for buy now flow)
}) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [showAdded, setShowAdded] = useState(false);

    const addItem = useCartStore(state => state.addItem);
    const initForTenant = useCartStore(state => state.initForTenant);

    const handleOpenModal = () => {
        // Initialize cart for this tenant (clears if different tenant)
        // Now includes tenantId for API submission
        initForTenant(tenantSlug, tenantWhatsapp, tenantName, tenantId);

        // If product has modifiers, show modal
        // If no modifiers, add directly to cart
        if (product.has_modifiers && product.modifier_groups?.length > 0) {
            setIsModalOpen(true);
        } else {
            handleQuickAdd();
        }
    };

    const handleQuickAdd = () => {
        addItem(product, 1, [], '');
        showAddedFeedback();

        // If buy-now variant, immediately open cart for checkout
        if (variant === 'buy-now' && onOpenCart) {
            setTimeout(() => onOpenCart(), 300);
        }
    };

    const handleAddFromModal = (quantity, selectedModifiers, notes) => {
        addItem(product, quantity, selectedModifiers, notes);
        setIsModalOpen(false);
        showAddedFeedback();

        // If buy-now variant, immediately open cart for checkout
        if (variant === 'buy-now' && onOpenCart) {
            setTimeout(() => onOpenCart(), 300);
        }
    };

    const showAddedFeedback = () => {
        setShowAdded(true);
        setTimeout(() => setShowAdded(false), 1500);
    };

    // Use CSS brand variables for dynamic theming
    const baseStyles = cn(
        "inline-flex items-center justify-center gap-2 font-medium transition-colors",
        showAdded
            ? "bg-background text-foreground"
            : "bg-[var(--primary-color,#020617)] hover:brightness-90 text-[var(--primary-text-color,#ffffff)]",
        "shadow-lg hover:shadow-primary/20",
        "active:scale-95"
    );

    const variants = {
        default: cn(baseStyles, "px-4 py-3 rounded-xl text-sm"),
        compact: cn(baseStyles, "px-3 py-2 rounded-lg text-xs"),
        'icon-only': cn(baseStyles, "size-10 rounded-full p-0"),
        'buy-now': cn(baseStyles, "px-4 py-3 rounded-xl text-sm bg-background text-foreground hover:bg-muted"),
    };

    const getButtonContent = () => {
        if (showAdded) {
            return (
                <>
                    <Check className="w-4 h-4" />
                    {variant !== 'icon-only' && <span>¡Agregado!</span>}
                </>
            );
        }

        if (variant === 'icon-only') {
            return <Plus className="w-5 h-5" />;
        }

        if (variant === 'buy-now') {
            return (
                <>
                    <Zap className="w-4 h-4" />
                    <span>Comprar Ahora</span>
                </>
            );
        }

        return (
            <>
                <ShoppingCart className="w-4 h-4" />
                <span>Agregar</span>
            </>
        );
    };

    return (
        <>
            <button
                type="button"
                onClick={handleOpenModal}
                data-testid={`add-to-cart-${product.id}`}
                className={cn(variants[variant], className)}
                disabled={showAdded}
            >
                {getButtonContent()}
            </button>

            {/* Product Modal for modifier selection */}
            <ProductModal
                isOpen={isModalOpen}
                onClose={() => setIsModalOpen(false)}
                product={product}
                onConfirm={handleAddFromModal}
            />
        </>
    );
}

