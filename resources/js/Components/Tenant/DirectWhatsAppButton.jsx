import { AlertCircle, MessageCircle } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { buildWhatsAppUrl, isValidWhatsAppPhone } from '@/Utils/phoneUtils';
import { cn } from '@/lib/utils';

function buildProductMessage(product, tenantName) {
    const productName = product?.name || 'Producto';
    const productPrice = typeof product?.price === 'number'
        ? product.price.toFixed(2)
        : product?.price || '0.00';

    return `Hola ${tenantName || 'equipo'}!

Me interesa:

${productName}
Precio: L. ${productPrice}

Esta disponible?`;
}

function trackConversion(tenantId, productId = null) {
    if (!tenantId) {
        return;
    }

    fetch('/api/analytics/track', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
        body: JSON.stringify({
            tenant_id: tenantId,
            type: 'whatsapp_click',
            product_id: productId,
        }),
    }).catch(() => {});
}

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
    const size = variant === 'compact' ? 'sm' : 'default';

    if (!hasValidPhone || !whatsappUrl) {
        return (
            <Button variant="secondary" size={size} disabled className={cn('justify-center', className)}>
                <AlertCircle data-icon="inline-start" aria-hidden="true" />
                No disponible
            </Button>
        );
    }

    return (
        <Button asChild size={size} className={cn('justify-center', className)}>
            <a
                href={whatsappUrl}
                target="_blank"
                rel="noopener noreferrer"
                onClick={() => trackConversion(tenantId, product?.id)}
            >
                <MessageCircle data-icon="inline-start" aria-hidden="true" />
                Ordenar
            </a>
        </Button>
    );
}
