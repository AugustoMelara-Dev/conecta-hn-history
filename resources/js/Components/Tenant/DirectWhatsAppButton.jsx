/**
 * DirectWhatsAppButton - stateless component for Semilla tier.
 *
 * Purpose: immediate redirect to WhatsApp without cart interaction.
 */

import { AlertCircle, MessageCircle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import { buildWhatsAppUrl, isValidWhatsAppPhone } from '@/Utils/phoneUtils';

const buildProductMessage = (product, tenantName) => {
    const productName = product?.name || 'Producto';
    const productPrice = typeof product?.price === 'number'
        ? product.price.toFixed(2)
        : product?.price || '0.00';

    return `*Hola ${tenantName || 'equipo'}!*

Me interesa:

*${productName}*
Precio: L. ${productPrice}

¿Está disponible?`;
};

const trackConversion = (tenantId, productId = null) => {
    if (!tenantId) {
        return;
    }

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
    }).catch(() => {});
};

export default function DirectWhatsAppButton({
    product,
    tenantWhatsapp,
    tenantName,
    tenantId,
    variant = 'default',
    className,
}) {
    const hasValidPhone = isValidWhatsAppPhone(tenantWhatsapp);
    const message = buildProductMessage(product, tenantName);
    const whatsappUrl = buildWhatsAppUrl(tenantWhatsapp, message);

    const variants = {
        default: 'h-auto px-4 py-3 text-sm',
        compact: 'h-auto px-3 py-2 text-xs',
        floating: 'h-auto w-full py-4 text-base font-bold',
    };

    const handleClick = () => {
        trackConversion(tenantId, product?.id);
    };

    if (!hasValidPhone || !whatsappUrl) {
        return (
            <Button
                type="button"
                variant="secondary"
                disabled
                className={cn(variants[variant], className)}
                title="Número de WhatsApp no disponible"
            >
                <AlertCircle data-icon="inline-start" aria-hidden="true" />
                <span>No disponible</span>
            </Button>
        );
    }

    return (
        <Button asChild className={cn(variants[variant], className)}>
            <a
                href={whatsappUrl}
                target="_blank"
                rel="noopener noreferrer"
                onClick={handleClick}
            >
                <MessageCircle data-icon="inline-start" aria-hidden="true" />
                <span>Ordenar</span>
            </a>
        </Button>
    );
}
