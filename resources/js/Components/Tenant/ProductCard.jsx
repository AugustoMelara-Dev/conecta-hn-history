/**
 * ProductCard - Shared Product Card Component for Both Tiers
 * 
 * This component handles product display and uses ProductActionArea
 * for tier-specific action buttons (WhatsApp vs Cart).
 */

import { ShoppingBag } from 'lucide-react';
import ProductActionArea from './ProductActionArea';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';
import { cn } from '@/lib/utils';

export default function ProductCard({
    product,
    tenant,
    tenantPlan,
    variant = 'default', // 'default' | 'compact' | 'horizontal'
    className,
}) {
    const hasModifiers = product.has_modifiers || (product.modifier_groups?.length > 0);

    return (
        <Card className={cn(
            'group overflow-hidden border-border bg-card text-card-foreground shadow-sm transition-colors hover:border-primary/30',
            variant === 'horizontal'
                ? 'flex h-32 rounded-xl md:h-40'
                : 'rounded-2xl',
            className
        )}>
            {/* Product Image */}
            <div className={cn(
                'relative overflow-hidden bg-muted',
                variant === 'horizontal'
                    ? 'h-full w-32 shrink-0 md:w-40'
                    : 'aspect-[4/3]'
            )}>
                <img
                    src={product.image}
                    alt={product.name}
                    className="h-full w-full object-cover opacity-90 transition-transform duration-500 group-hover:scale-105"
                    loading="lazy"
                />

                {/* Price Badge */}
                <Badge className="absolute right-3 top-3 rounded-full border border-border/60 bg-background/90 px-3 py-1.5 text-sm font-bold text-foreground shadow-sm backdrop-blur-md hover:bg-background/90">
                    {hasModifiers && tenantPlan !== 'semilla' ? 'Desde ' : ''}
                    {product.formatted_price || `L. ${product.price}`}
                </Badge>

                {/* Modifiers badge - only for Pro */}
                {hasModifiers && tenantPlan !== 'semilla' && (
                    <Badge variant="secondary" className="absolute bottom-3 left-3 rounded-full px-2 py-1 text-xs font-medium backdrop-blur-md">
                        {product.modifier_groups?.length || 0} opciones
                    </Badge>
                )}
            </div>

            {/* Product Info */}
            <CardContent className={cn(
                'flex flex-col',
                variant === 'horizontal' ? 'flex-1 justify-between p-4' : 'p-4'
            )}>
                <div>
                    <h3 className="mb-1 truncate font-bold text-foreground transition-colors group-hover:text-primary">
                        {product.name}
                    </h3>
                    <p className={cn(
                        'line-clamp-2 text-sm text-muted-foreground',
                        variant === 'horizontal' ? 'mt-1' : 'mb-4 min-h-[2.5rem]'
                    )}>
                        {product.description || 'Delicioso producto disponible.'}
                    </p>
                </div>

                {/* Action Button - Strategy Pattern */}
                <div className={cn(variant === 'horizontal' ? 'mt-auto' : '')}>
                    <ProductActionArea
                        product={product}
                        tenantPlan={tenantPlan}
                        tenantWhatsapp={tenant.whatsapp || tenant.whatsapp_number}
                        tenantName={tenant.name}
                        tenantSlug={tenant.slug}
                        variant={variant === 'horizontal' ? 'compact' : 'default'}
                        className="w-full"
                    />
                </div>
            </CardContent>
        </Card>
    );
}
