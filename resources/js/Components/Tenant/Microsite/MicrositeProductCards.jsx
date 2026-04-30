import { Link } from '@inertiajs/react';
import ProductActionArea from '@/Components/Tenant/ProductActionArea';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardTitle,
} from '@/Components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyIcon,
    EmptyTitle,
} from '@/Components/ui/empty';
import { ShoppingBag } from 'lucide-react';

function stripPaginationLabel(label) {
    return String(label ?? '')
        .replace(/&laquo;/g, 'Anterior')
        .replace(/&raquo;/g, 'Siguiente')
        .replace(/<[^>]+>/g, '')
        .trim();
}

export function StorefrontProductCard({ product, tenant, tenantPlan }) {
    return (
        <Card className="flex h-full flex-col overflow-hidden rounded-[1.7rem] shadow-sm transition-transform hover:-translate-y-1">
            <div className="relative aspect-[4/3] overflow-hidden bg-muted">
                {product.image ? (
                    <img
                        src={product.image}
                        alt={product.name}
                        className="h-full w-full object-cover transition-transform duration-500 hover:scale-105"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <ShoppingBag className="size-12 text-muted-foreground" aria-hidden="true" />
                    </div>
                )}

                <Badge className="absolute right-4 top-4 rounded-full bg-foreground/70 px-3 py-1.5 text-sm font-bold text-background backdrop-blur-sm hover:bg-foreground/70">
                    {product.formatted_price || `L. ${product.price}`}
                </Badge>
            </div>

            <CardContent className="flex flex-1 flex-col gap-4 p-5">
                <div>
                    <CardTitle className="font-headline text-xl">
                        {product.name}
                    </CardTitle>
                    <CardDescription className="mt-2 min-h-[3.5rem] leading-7">
                        {product.description || 'Disponible para cotizar o comprar desde este micrositio.'}
                    </CardDescription>
                </div>
            </CardContent>

            <CardFooter className="mt-auto p-5 pt-0">
                <ProductActionArea
                    product={product}
                    tenantId={tenant.id}
                    tenantPlan={tenantPlan}
                    tenantWhatsapp={tenant.whatsapp || tenant.whatsapp_number}
                    tenantName={tenant.name}
                    tenantSlug={tenant.slug}
                    className="w-full"
                />
            </CardFooter>
        </Card>
    );
}

export function PaginationBar({ pagination = [] }) {
    if (!Array.isArray(pagination) || pagination.length <= 3) {
        return null;
    }

    return (
        <nav className="flex flex-wrap gap-2" aria-label="Paginación del catálogo">
            {pagination.map((link, index) => {
                const label = stripPaginationLabel(link.label);
                if (!label) {
                    return null;
                }

                return link.url ? (
                    <Button
                        key={`${label}-${index}`}
                        asChild
                        variant={link.active ? 'default' : 'outline'}
                        size="sm"
                        className="min-w-[2.75rem] rounded-full px-4 font-label text-xs font-semibold uppercase tracking-[0.16em]"
                        aria-current={link.active ? 'page' : undefined}
                    >
                        <Link href={link.url} preserveScroll preserveState>
                            {label}
                        </Link>
                    </Button>
                ) : (
                    <Button
                        key={`${label}-${index}`}
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled
                        className="min-w-[2.75rem] rounded-full px-4 font-label text-xs font-semibold uppercase tracking-[0.16em]"
                    >
                        {label}
                    </Button>
                );
            })}
        </nav>
    );
}

export function MicrositeEmptyState({ icon, title, description }) {
    return (
        <Empty className="rounded-[1.8rem] border-border bg-muted py-16">
            <EmptyHeader>
                <EmptyIcon icon={icon} className="bg-background text-muted-foreground" />
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription className="leading-7">
                    {description}
                </EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}
