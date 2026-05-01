import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Eye,
    EyeOff,
    Image as ImageIcon,
    PackageOpen,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';

import AppPageHeader from '@/Components/App/AppPageHeader';
import DataToolbar from '@/Components/App/DataToolbar';
import DestructiveAction from '@/Components/App/DestructiveAction';
import EntityEmptyState from '@/Components/App/EntityEmptyState';
import QuotaProgress from '@/Components/App/QuotaProgress';
import TenantLayout from '@/Layouts/TenantLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardFooter } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';
import { cn } from '@/lib/utils';

const DEBOUNCE_MS = 300;

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 2,
});

function ProductCard({ product, onToggleVisibility, onRequestDelete }) {
    const visibilityLabel = product.is_visible ? 'Ocultar producto' : 'Mostrar producto';

    return (
        <Card className="overflow-hidden">
            <div className="relative aspect-square overflow-hidden bg-muted">
                {product.image_url ? (
                    <img
                        src={product.image_url}
                        alt={product.name}
                        className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-muted-foreground">
                        <ImageIcon aria-hidden="true" className="size-12" />
                    </div>
                )}

                <Badge className="absolute right-2 top-2" variant={product.is_visible ? 'default' : 'secondary'}>
                    {product.is_visible ? 'Visible' : 'Oculto'}
                </Badge>
            </div>

            <CardContent className="flex flex-col gap-2 p-4">
                <h3 className="line-clamp-2 min-h-10 font-semibold text-foreground">{product.name}</h3>
                <p className="text-lg font-semibold text-primary">{currencyFormatter.format(product.price)}</p>
            </CardContent>

            <CardFooter className="gap-2 p-4 pt-0">
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                className="flex-1"
                                aria-label={visibilityLabel}
                                onClick={() => onToggleVisibility(product.id)}
                            >
                                {product.is_visible ? (
                                    <EyeOff data-icon="inline-start" aria-hidden="true" />
                                ) : (
                                    <Eye data-icon="inline-start" aria-hidden="true" />
                                )}
                                {product.is_visible ? 'Ocultar' : 'Mostrar'}
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{visibilityLabel}</TooltipContent>
                    </Tooltip>

                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button asChild variant="ghost" size="icon" aria-label={`Editar ${product.name}`}>
                                <Link href={route('tenant.products.edit', product.id)}>
                                    <Pencil aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Editar producto</TooltipContent>
                    </Tooltip>

                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="text-destructive hover:text-destructive"
                                aria-label={`Eliminar ${product.name}`}
                                onClick={() => onRequestDelete(product)}
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Eliminar producto</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </CardFooter>
        </Card>
    );
}

function Pagination({ links }) {
    if (!links || links.length <= 3) {
        return null;
    }

    const normalizeLabel = (label) => String(label || '')
        .replace(/&laquo;|&lsaquo;|Previous/gi, 'Anterior')
        .replace(/&raquo;|&rsaquo;|Next/gi, 'Siguiente')
        .replace(/<[^>]*>/g, '');

    return (
        <nav className="mt-8 flex items-center justify-center gap-1" aria-label="Paginacion de productos">
            {links.map((link, index) => (
                <Button
                    key={`${link.label}-${index}`}
                    asChild={Boolean(link.url)}
                    variant={link.active ? 'default' : 'outline'}
                    size="sm"
                    disabled={!link.url}
                    className={cn(!link.url && 'pointer-events-none opacity-50')}
                >
                    {link.url ? (
                        <Link href={link.url} preserveState preserveScroll>
                            {normalizeLabel(link.label)}
                        </Link>
                    ) : (
                        <span>{normalizeLabel(link.label)}</span>
                    )}
                </Button>
            ))}
        </nav>
    );
}

function SkeletonGrid({ count = 8 }) {
    return (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4" aria-label="Cargando productos">
            {Array.from({ length: count }).map((_, index) => (
                <Card key={index} className="overflow-hidden">
                    <Skeleton className="aspect-square rounded-none" />
                    <CardContent className="flex flex-col gap-3 p-4">
                        <Skeleton className="h-4 w-3/4" />
                        <Skeleton className="h-5 w-1/2" />
                    </CardContent>
                    <CardFooter className="gap-2 p-4 pt-0">
                        <Skeleton className="h-10 flex-1" />
                        <Skeleton className="size-10" />
                        <Skeleton className="size-10" />
                    </CardFooter>
                </Card>
            ))}
        </div>
    );
}

export default function ProductsIndex({ products, filters, limits, tenant }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [optimisticProducts, setOptimisticProducts] = useState(products.data);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [isSearching, setIsSearching] = useState(false);
    const isFirstRender = useRef(true);

    useEffect(() => {
        setOptimisticProducts(products.data);
    }, [products.data]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        setIsSearching(true);
        const timer = setTimeout(() => {
            router.get(
                route('tenant.products.index'),
                { search: search || undefined },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onFinish: () => setIsSearching(false),
                },
            );
        }, DEBOUNCE_MS);

        return () => clearTimeout(timer);
    }, [search]);

    const handleToggleVisibility = useCallback((productId) => {
        setOptimisticProducts((previousProducts) => previousProducts.map((product) => (
            product.id === productId ? { ...product, is_visible: !product.is_visible } : product
        )));

        router.put(
            route('tenant.products.toggle-visibility', productId),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setOptimisticProducts(products.data),
            },
        );
    }, [products.data]);

    const handleConfirmDelete = useCallback(() => {
        if (!deleteTarget) {
            return;
        }

        router.delete(route('tenant.products.destroy', deleteTarget.id), {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    }, [deleteTarget]);

    const canCreate = Boolean(limits?.can_create);

    return (
        <TenantLayout>
            <Head title="Mis Productos" />

            <div className="min-h-screen bg-muted/30">
                <AppPageHeader
                    title="Mis Productos"
                    backHref={`/app/${tenant?.slug}`}
                    backLabel="Volver al panel"
                    meta={(
                        <QuotaProgress
                            current={limits?.current_count || 0}
                            limit={limits?.limit || 10}
                            percentage={limits?.percentage || 0}
                            label="productos"
                        />
                    )}
                />

                <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    <DataToolbar
                        actions={canCreate ? (
                            <Button asChild>
                                <Link href={route('tenant.products.create')}>
                                    <Plus data-icon="inline-start" aria-hidden="true" />
                                    Nuevo Producto
                                </Link>
                            </Button>
                        ) : (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button type="button" disabled>
                                            <Plus data-icon="inline-start" aria-hidden="true" />
                                            Nuevo Producto
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Limite del plan alcanzado. Mejora tu plan para agregar mas productos.
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}
                    >
                        <label htmlFor="product-search" className="sr-only">Buscar productos</label>
                        <div className="relative max-w-md">
                            <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="product-search"
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Buscar productos..."
                                className="pl-10"
                            />
                        </div>
                    </DataToolbar>

                    {isSearching ? (
                        <SkeletonGrid count={8} />
                    ) : optimisticProducts.length > 0 ? (
                        <>
                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                {optimisticProducts.map((product) => (
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        onToggleVisibility={handleToggleVisibility}
                                        onRequestDelete={setDeleteTarget}
                                    />
                                ))}
                            </div>
                            <Pagination links={products.links} />
                        </>
                    ) : (
                        <EntityEmptyState
                            icon={PackageOpen}
                            title={search ? 'No se encontraron productos' : 'Aun no tienes productos'}
                            description={search
                                ? 'Prueba con otro termino de busqueda o limpia el filtro para ver todo el catalogo.'
                                : 'Agrega tu primer producto para que aparezca en la vitrina publica del negocio.'}
                            actionHref={!search && canCreate ? route('tenant.products.create') : undefined}
                            actionLabel={!search && canCreate ? 'Crear mi primer producto' : undefined}
                        />
                    )}
                </main>
            </div>

            <DestructiveAction
                open={Boolean(deleteTarget)}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Eliminar producto"
                description={deleteTarget
                    ? `Vas a eliminar "${deleteTarget.name}". Esta accion no se puede deshacer.`
                    : 'Esta accion no se puede deshacer.'}
                confirmLabel="Eliminar producto"
                onConfirm={handleConfirmDelete}
            />
        </TenantLayout>
    );
}
