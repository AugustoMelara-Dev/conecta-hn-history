import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Minus,
    Plus,
    ShoppingCart,
    Trash2,
} from 'lucide-react';
import CategoryNav from '@/Components/Store/CategoryNav';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyIcon,
    EmptyTitle,
} from '@/Components/ui/empty';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import useCartStore from '@/Stores/useCartStore';
import { formatCurrency } from '@/Utils/currency';

export default function StoreLayout({ children, className = '' }) {
    const { props } = usePage();
    const tenant = props.tenant ?? {};
    const primaryColor = tenant.primary_color || '#000000';
    const menuSections = tenant.menu_sections || [];

    const {
        items,
        isCartOpen,
        toggleCart,
        closeCart,
        removeItem,
        updateQuantity,
        getTotalItems,
        getSubtotal,
    } = useCartStore();

    const totalItems = getTotalItems();
    const subtotal = getSubtotal();
    const checkoutHref = tenant.slug ? `/t/${tenant.slug}/checkout` : '/checkout';

    return (
        <div
            className="min-h-screen bg-background"
            style={{ '--store-primary': primaryColor }}
        >
            <header className="sticky top-0 z-50 border-b bg-background shadow-sm">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between">
                        <Link
                            href={tenant.slug ? `/t/${tenant.slug}` : '/'}
                            className="inline-flex items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            {tenant.logo ? (
                                <img
                                    src={tenant.logo}
                                    alt={tenant.name || 'Tienda'}
                                    className="h-10 w-auto object-contain"
                                />
                            ) : (
                                <span className="text-xl font-bold text-[color:var(--store-primary)]">
                                    {tenant.name || 'Tienda'}
                                </span>
                            )}
                        </Link>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={toggleCart}
                            className="relative rounded-full"
                            aria-label={`Carrito de compras. ${totalItems} artículos`}
                            aria-expanded={isCartOpen}
                            aria-controls="store-cart-sheet"
                        >
                            <ShoppingCart data-icon aria-hidden="true" />
                            {totalItems > 0 ? (
                                <Badge className="absolute -right-1 -top-1 flex size-5 items-center justify-center rounded-full bg-[color:var(--store-primary)] p-0 text-xs text-white">
                                    {totalItems > 99 ? '99+' : totalItems}
                                </Badge>
                            ) : null}
                        </Button>
                    </div>
                </div>
            </header>

            <CategoryNav
                categories={menuSections}
                primaryColor={primaryColor}
            />

            <main className={className}>{children}</main>

            <Sheet
                open={isCartOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeCart();
                    }
                }}
            >
                <SheetContent className="flex h-full w-full max-w-md flex-col p-0">
                    <div id="store-cart-sheet" className="sr-only" aria-live="polite">
                        {totalItems} articulos en el carrito
                    </div>
                    <SheetHeader className="border-b px-4 py-4 pr-12 text-left">
                        <SheetTitle>
                            Tu carrito
                            {totalItems > 0 ? (
                                <span className="ml-2 text-sm font-normal text-muted-foreground">
                                    ({totalItems} artículo{totalItems !== 1 ? 's' : ''})
                                </span>
                            ) : null}
                        </SheetTitle>
                        <SheetDescription>
                            Revisa tus productos antes de finalizar la compra.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 overflow-y-auto px-4 py-4">
                        {items.length === 0 ? (
                            <Empty className="h-full min-h-96 border-0">
                                <EmptyHeader>
                                    <EmptyIcon icon={ShoppingCart} />
                                    <EmptyTitle>Tu carrito está vacío</EmptyTitle>
                                    <EmptyDescription>
                                        Agrega productos para comenzar tu pedido.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <ul className="divide-y" aria-label="Productos en el carrito">
                                {items.map((item) => (
                                    <li key={item.id} className="flex items-start gap-4 py-4">
                                        {item.image ? (
                                            <img
                                                src={item.image}
                                                alt={item.name}
                                                className="size-16 rounded-lg border object-cover"
                                            />
                                        ) : null}

                                        <div className="min-w-0 flex-1">
                                            <h4 className="truncate text-sm font-medium text-foreground">
                                                {item.name}
                                            </h4>

                                            {item.selectedModifiers?.length > 0 ? (
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {item.selectedModifiers
                                                        .map((modifier) => modifier.name)
                                                        .join(', ')}
                                                </p>
                                            ) : null}

                                            <p className="mt-1 text-sm font-medium text-[color:var(--store-primary)]">
                                                {formatCurrency(item.price * item.quantity)}
                                            </p>

                                            <div className="mt-2 flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-7 rounded-full"
                                                    onClick={() => updateQuantity(item.id, item.quantity - 1)}
                                                    aria-label={`Reducir cantidad de ${item.name}`}
                                                >
                                                    <Minus data-icon aria-hidden="true" />
                                                </Button>
                                                <span className="w-6 text-center text-sm font-medium">
                                                    {item.quantity}
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-7 rounded-full"
                                                    onClick={() => updateQuantity(item.id, item.quantity + 1)}
                                                    aria-label={`Aumentar cantidad de ${item.name}`}
                                                >
                                                    <Plus data-icon aria-hidden="true" />
                                                </Button>
                                            </div>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 text-muted-foreground hover:text-destructive"
                                            onClick={() => removeItem(item.id)}
                                            aria-label={`Eliminar ${item.name} del carrito`}
                                        >
                                            <Trash2 data-icon aria-hidden="true" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {items.length > 0 ? (
                        <SheetFooter className="border-t px-4 py-4 sm:flex-col sm:items-stretch sm:justify-start">
                            <div className="flex items-center justify-between text-base font-medium text-foreground">
                                <span>Subtotal</span>
                                <span>{formatCurrency(subtotal)}</span>
                            </div>

                            <p className="text-sm text-muted-foreground">
                                Envío calculado al finalizar la compra.
                            </p>

                            <Button
                                asChild
                                className="w-full bg-[color:var(--store-primary)] text-white hover:opacity-90"
                            >
                                <Link href={checkoutHref} onClick={closeCart}>
                                    Finalizar compra
                                    <ArrowRight data-icon="inline-end" aria-hidden="true" />
                                </Link>
                            </Button>

                            <Button type="button" variant="ghost" onClick={closeCart} className="w-full">
                                Continuar comprando
                            </Button>
                        </SheetFooter>
                    ) : null}
                </SheetContent>
            </Sheet>
        </div>
    );
}
