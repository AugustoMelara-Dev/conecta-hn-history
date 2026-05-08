/**
 * ProductActionArea - Strategy Pattern Component
 *
 * Purpose: Decides which action button to render based on tenant plan.
 * This is a Higher-Order Component that contains ONLY business logic,
 * no presentation JSX.
 *
 * Strategy:
 * - Plan 'semilla' -> DirectWhatsAppButton (stateless, immediate WhatsApp)
 * - Plan 'pro'/'enterprise' -> AddToCartButton (stateful, cart + modifiers)
 *
 * TRANSACTIONAL HANDSHAKE: All cart actions now require tenantId
 * so orders can be persisted to the database before WhatsApp redirect.
 */

import DirectWhatsAppButton from './DirectWhatsAppButton';
import AddToCartButton from './AddToCartButton';

export default function ProductActionArea({
    product,
    tenantId, // Required for order API submission
    tenantPlan,
    tenantWhatsapp,
    tenantName,
    tenantSlug,
    variant = 'default',
    className,
    onOpenCart, // Callback to open cart for the buy now flow
}) {
    // Strategy Pattern: Select component based on plan tier
    if (tenantPlan === 'semilla') {
        return (
            <DirectWhatsAppButton
                product={product}
                tenantId={tenantId}
                tenantWhatsapp={tenantWhatsapp}
                tenantName={tenantName}
                variant={variant}
                className={className}
            />
        );
    }

    // Pro or Enterprise tier - Stateful cart experience with API integration
    return (
        <AddToCartButton
            product={product}
            tenantId={tenantId}
            tenantWhatsapp={tenantWhatsapp}
            tenantName={tenantName}
            tenantSlug={tenantSlug}
            variant={variant}
            className={className}
            onOpenCart={onOpenCart}
        />
    );
}
