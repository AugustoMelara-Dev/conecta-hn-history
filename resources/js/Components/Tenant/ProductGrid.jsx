/**
 * ProductGrid - Shared Product Grid Component for Both Tiers
 *
 * Renders a grid of ProductCard components with empty state handling.
 */

import { ShoppingBag } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
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
            <Card className="border-dashed text-center">
                <CardHeader>
                    <span className="mx-auto grid size-12 place-items-center rounded-md bg-muted text-muted-foreground">
                        <ShoppingBag className="size-5" aria-hidden="true" />
                    </span>
                    <CardTitle>Catálogo vacío</CardTitle>
                    <CardDescription>{emptyMessage}</CardDescription>
                </CardHeader>
                <CardContent />
            </Card>
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
