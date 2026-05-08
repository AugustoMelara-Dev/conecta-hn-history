import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Eye,
    EyeOff,
    Package,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';

import TenantLayout from '@/Layouts/TenantLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    Field,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Progress } from '@/Components/ui/progress';
import { Separator } from '@/Components/ui/separator';
import { Skeleton } from '@/Components/ui/skeleton';

const DEBOUNCE_MS = 300;
const workspaceProductsUrl = '/workspace/products';
const productUrl = (productId, suffix = '') => `${workspaceProductsUrl}/${productId}${suffix}`;

const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 2,
});

function paginationLabel(label = '') {
    return label
        .replace(/&laquo;|«/g, 'Anterior')
        .replace(/&raquo;|»/g, 'Siguiente')
        .replace(/<[^>]*>/g, '')
        .trim();
}

function QuotaIndicator({ current, limit, percentage }) {
    const hasNumericLimit = Number.isFinite(Number(limit)) && Number(limit) > 0;
    const safePercentage = Math.min(Math.max(Number(percentage) || 0, 0), 100);

    return (
        <Card className="hidden min-w-[260px] md:block">
            <CardContent className="flex flex-col gap-3 p-4">
                <div className="flex items-center justify-between gap-3 text-sm">
                    <span className="font-medium text-foreground">Uso del catálogo</span>
                    <Badge variant={safePercentage >= 80 ? 'destructive' : 'secondary'}>
                        {current} / {hasNumericLimit ? limit : 'sin límite'}
                    </Badge>
                </div>
                {hasNumericLimit ? (
                    <Progress value={safePercentage} aria-label="Uso del límite de productos" />
                ) : null}
            </CardContent>
        </Card>
    );
}

function ProductImage({ product }) {
    if (product.image_url) {
        return (
            <img
                src={product.image_url}
                alt={product.name}
                className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                loading="lazy"
            />
        );
    }

    return (
        <div className="flex h-full w-full items-center justify-center bg-muted text-muted-foreground">
            <Package className="size-12" aria-hidden="true" />
        </div>
    );
}

function ProductCard({ product, onToggleVisibility, onDelete }) {
    return (
        <Card data-testid="products-card" className="group flex h-full flex-col overflow-hidden">
            <div className="relative aspect-square overflow-hidden bg-muted">
                <ProductImage product={product} />
                <Badge
                    variant={product.is_visible ? 'default' : 'secondary'}
                    className="absolute right-3 top-3"
                >
                    {product.is_visible ? 'Visible' : 'Oculto'}
                </Badge>
            </div>

            <CardHeader className="gap-2">
                <CardTitle className="line-clamp-2 text-base">{product.name}</CardTitle>
                <CardDescription className="line-clamp-2">
                    {product.description || 'Sin descripción pública todavía.'}
                </CardDescription>
            </CardHeader>

            <CardContent className="mt-auto">
                <p className="text-lg font-semibold text-foreground">
                    {currencyFormatter.format(Number(product.price) || 0)}
                </p>
            </CardContent>

            <CardFooter className="grid grid-cols-[1fr_auto_auto] gap-2">
                <Button
                    type="button"
                    variant={product.is_visible ? 'secondary' : 'outline'}
                    onClick={() => onToggleVisibility(product.id)}
                    aria-label={product.is_visible ? `Ocultar ${product.name}` : `Mostrar ${product.name}`}
                >
                    {product.is_visible ? (
                        <EyeOff data-icon="inline-start" aria-hidden="true" />
                    ) : (
                        <Eye data-icon="inline-start" aria-hidden="true" />
                    )}
                    {product.is_visible ? 'Ocultar' : 'Mostrar'}
                </Button>

                <Button variant="outline" size="icon" asChild>
                    <Link
                        href={productUrl(product.id, '/edit')}
                        data-testid="products-edit-cta"
                        aria-label={`Editar ${product.name}`}
                    >
                        <Pencil aria-hidden="true" />
                    </Link>
                </Button>

                <Button
                    type="button"
                    variant="destructive"
                    size="icon"
                    onClick={() => onDelete(product)}
                    aria-label={`Eliminar ${product.name}`}
                >
                    <Trash2 aria-hidden="true" />
                </Button>
            </CardFooter>
        </Card>
    );
}

function Pagination({ links }) {
    if (!links || links.length <= 3) return null;

    return (
        <nav className="mt-8 flex flex-wrap items-center justify-center gap-2" aria-label="Paginación de productos">
            {links.map((link, index) => {
                const label = paginationLabel(link.label);

                if (!link.url) {
                    return (
                        <Button key={`${label}-${index}`} variant="outline" size="sm" disabled>
                            {label}
                        </Button>
                    );
                }

                return (
                    <Button key={`${label}-${index}`} variant={link.active ? 'default' : 'outline'} size="sm" asChild>
                        <Link href={link.url} preserveState>
                            {label}
                        </Link>
                    </Button>
                );
            })}
        </nav>
    );
}

function ProductSkeleton() {
    return (
        <Card className="overflow-hidden">
            <Skeleton className="aspect-square rounded-none" />
            <CardHeader>
                <Skeleton className="h-5 w-3/4" />
                <Skeleton className="h-4 w-full" />
            </CardHeader>
            <CardContent>
                <Skeleton className="h-6 w-1/2" />
            </CardContent>
            <CardFooter className="grid grid-cols-[1fr_auto_auto] gap-2">
                <Skeleton className="h-10" />
                <Skeleton className="size-10" />
                <Skeleton className="size-10" />
            </CardFooter>
        </Card>
    );
}

function SkeletonGrid({ count = 8 }) {
    return (
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {Array.from({ length: count }).map((_, index) => (
                <ProductSkeleton key={index} />
            ))}
        </div>
    );
}

function EmptyProducts({ search, canCreate }) {
    return (
        <Card className="mx-auto max-w-2xl text-center">
            <CardHeader className="items-center">
                <div className="mb-2 flex size-16 items-center justify-center rounded-full bg-muted text-muted-foreground">
                    <Package className="size-8" aria-hidden="true" />
                </div>
                <CardTitle>
                    {search ? 'No se encontraron productos' : 'Aún no tienes productos'}
                </CardTitle>
                <CardDescription>
                    {search
                        ? 'Prueba con otro término de búsqueda o limpia el filtro para volver al catálogo completo.'
                        : 'Comienza añadiendo tu primer producto a la vitrina de tu negocio.'}
                </CardDescription>
            </CardHeader>
            {!search && canCreate ? (
                <CardFooter className="justify-center">
                    <Button asChild data-testid="products-create-cta">
                        <Link href={`${workspaceProductsUrl}/create`}>
                            <Plus data-icon="inline-start" aria-hidden="true" />
                            Crear mi primer producto
                        </Link>
                    </Button>
                </CardFooter>
            ) : null}
        </Card>
    );
}

export default function ProductsIndex({ products, filters, limits, tenant }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [optimisticProducts, setOptimisticProducts] = useState(products.data || []);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [isSearching, setIsSearching] = useState(false);
    const isFirstRender = useRef(true);

    const currentCount = limits?.current_count ?? products.total ?? optimisticProducts.length;
    const limit = limits?.limit ?? null;
    const percentage = limits?.percentage ?? (limit ? (currentCount / limit) * 100 : 0);
    const canCreate = limits?.can_create ?? true;
    const backHref = tenant?.slug ? `/app/${tenant.slug}` : route('dashboard');

    const productCountLabel = useMemo(() => {
        const count = products.total ?? optimisticProducts.length;
        return count === 1 ? '1 producto' : `${count} productos`;
    }, [optimisticProducts.length, products.total]);

    useEffect(() => {
        setOptimisticProducts(products.data || []);
    }, [products.data]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        setIsSearching(true);
        const timer = setTimeout(() => {
            router.get(
                workspaceProductsUrl,
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
        setOptimisticProducts((previousProducts) =>
            previousProducts.map((product) =>
                product.id === productId ? { ...product, is_visible: !product.is_visible } : product,
            ),
        );

        router.put(
            productUrl(productId, '/toggle-visibility'),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => setOptimisticProducts(products.data || []),
            },
        );
    }, [products.data]);

    const confirmDelete = useCallback(() => {
        if (!deleteTarget) return;

        router.delete(productUrl(deleteTarget.id), {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    }, [deleteTarget]);

    return (
        <TenantLayout>
            <Head title="Mis productos" />

            <main data-testid="products-page" className="min-h-screen bg-background">
                <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
                    <header className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                        <div className="flex min-w-0 flex-col gap-4">
                            <Button variant="ghost" className="w-fit px-0" asChild>
                                <Link href={backHref}>
                                    <ArrowLeft data-icon="inline-start" aria-hidden="true" />
                                    Volver al panel
                                </Link>
                            </Button>
                            <div className="flex flex-col gap-2">
                                <Badge variant="secondary" className="w-fit">
                                    {productCountLabel}
                                </Badge>
                                <h1 className="text-3xl font-semibold tracking-normal text-foreground">
                                    Mis productos
                                </h1>
                                <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                                    Administra la vitrina de tu negocio, controla qué productos están visibles y mantén el catálogo listo para vender.
                                </p>
                            </div>
                        </div>

                        <QuotaIndicator current={currentCount} limit={limit} percentage={percentage} />
                    </header>

                    <Separator />

                    <section className="flex flex-col gap-4 rounded-lg border bg-card p-4 text-card-foreground md:flex-row md:items-center md:justify-between">
                        <form className="w-full md:max-w-md" role="search" onSubmit={(event) => event.preventDefault()}>
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor="products-search" className="sr-only">
                                        Buscar productos
                                    </FieldLabel>
                                    <div className="relative">
                                        <Search
                                            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            id="products-search"
                                            data-testid="products-search"
                                            value={search}
                                            onChange={(event) => setSearch(event.target.value)}
                                            placeholder="Buscar productos..."
                                            className="pl-9"
                                        />
                                    </div>
                                </Field>
                            </FieldGroup>
                        </form>

                        {canCreate ? (
                            <Button asChild data-testid="products-create-cta">
                                <Link href={`${workspaceProductsUrl}/create`}>
                                    <Plus data-icon="inline-start" aria-hidden="true" />
                                    Nuevo producto
                                </Link>
                            </Button>
                        ) : (
                            <Card className="border-dashed md:max-w-sm">
                                <CardContent className="flex flex-col gap-2 p-4 text-sm text-muted-foreground">
                                    <span className="font-medium text-foreground">Límite del plan alcanzado</span>
                                    <span>Actualiza tu plan para publicar más productos en el catálogo.</span>
                                    {tenant?.slug ? (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={`/upgrade/${tenant.slug}`}>Ver opciones</Link>
                                        </Button>
                                    ) : null}
                                </CardContent>
                            </Card>
                        )}
                    </section>

                    <section aria-live="polite" aria-busy={isSearching}>
                        {isSearching ? (
                            <SkeletonGrid count={8} />
                        ) : optimisticProducts.length > 0 ? (
                            <>
                                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {optimisticProducts.map((product) => (
                                        <ProductCard
                                            key={product.id}
                                            product={product}
                                            onToggleVisibility={handleToggleVisibility}
                                            onDelete={setDeleteTarget}
                                        />
                                    ))}
                                </div>
                                <Pagination links={products.links} />
                            </>
                        ) : (
                            <EmptyProducts search={search} canCreate={canCreate} />
                        )}
                    </section>
                </div>
            </main>

            <Dialog open={Boolean(deleteTarget)} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <DialogContent data-testid="products-delete-dialog">
                    <DialogHeader>
                        <DialogTitle>Eliminar producto</DialogTitle>
                        <DialogDescription>
                            Esta acción eliminará {deleteTarget?.name ? `"${deleteTarget.name}"` : 'este producto'} de tu catálogo. No se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="button" variant="destructive" onClick={confirmDelete}>
                            Eliminar producto
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </TenantLayout>
    );
}
