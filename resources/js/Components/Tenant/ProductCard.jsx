/**
 * ProductCard - Shared Product Card Component for Both Tiers
 * 
 * This component handles product display and uses ProductActionArea
 * for tier-specific action buttons (WhatsApp vs Cart).
 */

import { ShoppingBag } from 'lucide-react';
import ProductActionArea from './ProductActionArea';
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
        <div className={cn(
            "group bg-slate-900 border border-white/5 overflow-hidden hover:border-indigo-500/30 transition-all",
            variant === 'horizontal'
                ? "rounded-xl flex h-32 md:h-40"
                : "rounded-2xl",
            className
        )}>
            {/* Product Image */}
            <div className={cn(
                "relative overflow-hidden bg-slate-800",
                variant === 'horizontal'
                    ? "w-32 md:w-40 h-full shrink-0"
                    : "aspect-[4/3]"
            )}>
                <img
                    src={product.image}
                    alt={product.name}
                    className="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all duration-500"
                    loading="lazy"
                />

                {/* Price Badge */}
                <div className="absolute top-3 right-3 bg-black/60 backdrop-blur-md px-3 py-1.5 rounded-full text-sm font-bold text-white border border-white/10">
                    {hasModifiers && tenantPlan !== 'semilla' ? 'Desde ' : ''}
                    {product.formatted_price || `L. ${product.price}`}
                </div>

                {/* Modifiers badge - only for Pro */}
                {hasModifiers && tenantPlan !== 'semilla' && (
                    <div className="absolute bottom-3 left-3 bg-indigo-600/90 backdrop-blur-md px-2 py-1 rounded-full text-xs font-medium text-white">
                        {product.modifier_groups?.length || 0} opciones
                    </div>
                )}
            </div>

            {/* Product Info */}
            <div className={cn(
                "flex flex-col",
                variant === 'horizontal' ? "flex-1 p-4 justify-between" : "p-4"
            )}>
                <div>
                    <h3 className="font-bold text-white mb-1 truncate group-hover:text-indigo-400 transition-colors">
                        {product.name}
                    </h3>
                    <p className={cn(
                        "text-sm text-slate-400 line-clamp-2",
                        variant === 'horizontal' ? "mt-1" : "mb-4 min-h-[2.5rem]"
                    )}>
                        {product.description || 'Delicioso producto disponible.'}
                    </p>
                </div>

                {/* Action Button - Strategy Pattern */}
                <div className={cn(variant === 'horizontal' ? "mt-auto" : "")}>
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
            </div>
        </div>
    );
}
