/**
 * DirectWhatsAppButton - Stateless Component for Semilla Tier
 * 
 * Purpose: Immediate redirect to WhatsApp without any cart interaction.
 * This component is "dumb" - it doesn't know about carts or stores.
 * 
 * HARDENED VERSION:
 * - Uses centralized phoneUtils for Honduras phone normalization
 * - Graceful degradation if phone number is invalid
 * 
 * @usage Only rendered for tenants with plan='semilla'
 */

import { MessageCircle, AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { buildWhatsAppUrl, isValidWhatsAppPhone } from '@/Utils/phoneUtils';

/**
 * Build the WhatsApp message for a single product order
 */
const buildProductMessage = (product, tenantName) => {
    // Safe accessor for product properties
    const productName = product?.name || 'Producto';
    const productPrice = typeof product?.price === 'number'
        ? product.price.toFixed(2)
        : product?.price || '0.00';

    return `🛒 *Hola ${tenantName || 'equipo'}!*

Me interesa:

📦 *${productName}*
💰 Precio: L. ${productPrice}

¿Está disponible?`;
};

/**
 * Fire-and-forget analytics tracking
 */
const trackConversion = (tenantId, productId = null) => {
    if (!tenantId) return;

    fetch('/api/v1/analytics/track', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            tenant_id: tenantId,
            type: 'whatsapp_click',
            product_id: productId,
        }),
    }).catch(() => { }); // Silently fail - don't block UX
};

export default function DirectWhatsAppButton({
    product,
    tenantWhatsapp,
    tenantName,
    tenantId, // NEW: Required for tracking
    variant = 'default', // 'default' | 'compact' | 'floating'
    className,
}) {
    // Check if we have a valid phone number
    const hasValidPhone = isValidWhatsAppPhone(tenantWhatsapp);

    // Build message and URL using centralized utility
    const message = buildProductMessage(product, tenantName);
    const whatsappUrl = buildWhatsAppUrl(tenantWhatsapp, message);

    // Track conversion on click
    const handleClick = () => {
        trackConversion(tenantId, product?.id);
    };

    const baseStyles = cn(
        "inline-flex items-center justify-center gap-2 font-medium transition-all",
        "shadow-lg active:scale-95"
    );

    const variants = {
        default: cn(baseStyles, "px-4 py-3 rounded-xl text-sm"),
        compact: cn(baseStyles, "px-3 py-2 rounded-lg text-xs"),
        floating: cn(baseStyles, "w-full py-4 rounded-2xl text-base font-bold"),
    };

    // If no valid phone, render a disabled state
    if (!hasValidPhone || !whatsappUrl) {
        return (
            <div
                className={cn(
                    variants[variant],
                    "bg-slate-700 text-slate-400 cursor-not-allowed opacity-60",
                    className
                )}
                title="Número de WhatsApp no disponible"
            >
                <AlertCircle className="w-4 h-4" />
                <span>No disponible</span>
            </div>
        );
    }

    return (
        <a
            href={whatsappUrl}
            target="_blank"
            rel="noopener noreferrer"
            onClick={handleClick}
            className={cn(
                variants[variant],
                "bg-emerald-600 hover:bg-emerald-500 text-white",
                "shadow-emerald-900/20 hover:shadow-emerald-500/30",
                className
            )}
        >
            <MessageCircle className="w-4 h-4" />
            <span>Ordenar</span>
        </a>
    );
}
