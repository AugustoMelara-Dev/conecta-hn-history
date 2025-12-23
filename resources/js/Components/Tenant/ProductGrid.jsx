/**
 * ProductGrid - Shared Product Grid Component for Both Tiers
 * 
 * Renders a grid of ProductCard components with empty state handling.
 */

import { ShoppingBag } from 'lucide-react';
import ProductCard from './ProductCard';
import { cn } from '@/lib/utils';

export default function ProductGrid({
    products = [],
    tenant,
    tenantPlan,
    columns = 'responsive', // 'responsive' | 2 | 3
    variant = 'default', // 'default' | 'horizontal'
    emptyMessage = 'No hay productos disponibles.',
    className,
}) {
    if (!products || products.length === 0) {
        return (
            <div className="text-center py-12 bg-slate-900/50 rounded-2xl border border-dashed border-white/10">
                <ShoppingBag className="w-12 h-12 text-slate-700 mx-auto mb-4" />
                <h3 className="text-lg font-medium text-white">Catálogo vacío</h3>
                <p className="text-slate-400">{emptyMessage}</p>
            </div>
        );
    }

    const gridClasses = columns === 'responsive'
        ? "grid-cols-1 sm:grid-cols-2 lg:grid-cols-3"
        : `grid-cols-1 md:grid-cols-${columns}`;

    return (
        <div className={cn("grid gap-4", gridClasses, className)}>
            {products.map((product) => (
                <ProductCard
                    key={product.id}
                    product={product}
                    tenant={tenant}
                    tenantPlan={tenantPlan}
                    variant={variant}
                />
            ))}
        </div>
    );
}
