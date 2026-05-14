/**
 * ProductCard - Shared Product Card Component for Both Tiers
 * 
 * This component handles product display and uses ProductActionArea
 * for tier-specific action buttons (WhatsApp vs Cart).
 */

import { ShoppingBag } from 'lucide-react';

import { Badge } from '@/Components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import ProductActionArea from './ProductActionArea';
import { cn } from '@/lib/utils';

function getPublicMediaUrl(path) {
    if (!path || typeof path !== 'string') {
        return null;
    }

    if (path.startsWith('http') || path.startsWith('/') || path.startsWith('data:')) {
        return path;
    }

    return `/storage/${path}`;
}

export default function ProductCard({
    product,
    tenant,
    tenantPlan,
    variant = 'default', // 'default' | 'compact' | 'horizontal'
    className,
}) {
    const hasModifiers = product.has_modifiers || (product.modifier_groups?.length > 0);
    const productImage = getPublicMediaUrl(product.image);

    return (
        <Card className={cn(
            "group flex overflow-hidden transition-shadow hover:shadow-md",
            variant === 'horizontal'
                ? "h-32 flex-row md:h-40"
                : "h-full flex-col",
            className
        )}>
            <div className={cn(
                "relative overflow-hidden bg-muted",
                variant === 'horizontal'
                    ? "h-full w-32 shrink-0 md:w-40"
                    : "aspect-[4/3]"
            )}>
                {productImage ? (
                    <img
                        src={productImage}
                        alt={product.name}
                        className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                        loading="lazy"
                    />
                ) : (
                    <div className="grid size-full place-items-center text-muted-foreground">
                        <ShoppingBag className="size-10 opacity-60" aria-hidden="true" />
                    </div>
                )}

                <Badge className="absolute right-3 top-3 shadow-sm">
                    {hasModifiers && tenantPlan !== 'semilla' ? 'Desde ' : ''}
                    {product.formatted_price || `L. ${product.price}`}
                </Badge>

                {hasModifiers && tenantPlan !== 'semilla' && (
                    <Badge variant="secondary" className="absolute bottom-3 left-3">
                        {product.modifier_groups?.length || 0} opciones
                    </Badge>
                )}
            </div>

            <div
                className={cn(
                    "flex flex-col",
                    variant === 'horizontal' ? "flex-1 justify-between" : "flex-1"
                )}
            >
                <CardHeader className="gap-2 p-4 pb-0">
                    <CardTitle className="truncate text-base leading-snug transition-colors group-hover:text-primary">
                        {product.name}
                    </CardTitle>
                    <CardDescription className={cn(
                        "line-clamp-2 text-sm text-muted-foreground",
                        variant !== 'horizontal' && "min-h-10"
                    )}>
                        {product.description || 'Producto disponible para consulta.'}
                    </CardDescription>
                </CardHeader>

                <CardContent className="flex-1 p-0" />

                <CardFooter className="p-4 pt-0">
                    <ProductActionArea
                        product={product}
                        tenantId={tenant?.id}
                        tenantPlan={tenantPlan}
                        tenantWhatsapp={tenant?.whatsapp || tenant?.whatsapp_number}
                        tenantName={tenant?.name}
                        tenantSlug={tenant?.slug}
                        variant={variant === 'horizontal' ? 'compact' : 'default'}
                        className="w-full"
                    />
                </CardFooter>
            </div>
        </Card>
    );
}
