import { Link } from '@inertiajs/react';
import { ArrowRight, MapPin, Store } from 'lucide-react';

import FavoriteButton from '@/Components/Public/FavoriteButton';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { cn } from '@/lib/utils';

function getTenantHref(tenant) {
    if (!tenant?.slug) {
        return route('directory.index');
    }

    try {
        if (route().has('tenant.microsite.show')) {
            return route('tenant.microsite.show', { tenant: tenant.slug });
        }
    } catch {
        // Ziggy can be stale in local builds; fall back to the directory.
    }

    return route('directory.index', { search: tenant.name || '' });
}

function getInitials(name) {
    const parts = String(name || 'Negocio')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    return parts
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('') || 'N';
}

function getImageUrl(value) {
    if (!value) {
        return null;
    }

    if (value.startsWith('http') || value.startsWith('/') || value.startsWith('data:')) {
        return value;
    }

    return `/storage/${value}`;
}

export default function TenantCard({ tenant, onFavoriteToggled }) {
    const name = tenant?.name || 'Negocio local';
    const category = typeof tenant?.category === 'string' ? tenant.category : tenant?.category?.name;
    const city = typeof tenant?.city === 'string' ? tenant.city : tenant?.city?.name;
    const coverUrl = getImageUrl(tenant?.cover_url || tenant?.banner_path);
    const logoUrl = getImageUrl(tenant?.logo_url || tenant?.logo_path);
    const isOpen = typeof tenant?.is_open === 'boolean' ? tenant.is_open : null;

    const href = getTenantHref(tenant);

    return (
        <Card
            className="group relative flex h-full flex-col overflow-hidden transition-shadow hover:shadow-md"
            data-testid="tenant-card"
        >
            <Link href={href} data-testid="primary-tenant-cta" aria-label={`Ver micrositio de ${name}`}>
                <div className="relative aspect-[16/9] bg-muted">
                    {coverUrl ? (
                        <img
                            src={coverUrl}
                            alt={`Portada de ${name}`}
                            loading="lazy"
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center text-muted-foreground">
                            <Store aria-hidden="true" />
                        </div>
                    )}

                    {isOpen !== null ? (
                        <Badge
                            className="absolute left-3 top-3"
                            variant={isOpen ? 'default' : 'secondary'}
                        >
                            {isOpen ? 'Abierto' : 'Cerrado'}
                        </Badge>
                    ) : null}
                </div>
            </Link>

            {tenant?.id ? (
                <div className="absolute right-3 top-3">
                    <FavoriteButton
                        tenantId={tenant.id}
                        isFavorited={Boolean(tenant.is_favorited)}
                        count={Number(tenant.favorites_count || 0)}
                        size="sm"
                        showCount={Boolean(tenant.favorites_count)}
                        onToggled={onFavoriteToggled}
                    />
                </div>
            ) : null}

            <CardHeader className="flex-row items-start gap-4 p-5 pb-3">
                <Avatar className="size-12 rounded-md">
                    <AvatarImage src={logoUrl || undefined} alt={`Logo de ${name}`} />
                    <AvatarFallback className="rounded-md">{getInitials(name)}</AvatarFallback>
                </Avatar>

                <div className="min-w-0 flex-1">
                    <Link href={href} className="block" data-testid="primary-tenant-cta">
                        <CardTitle className="truncate text-base leading-6 group-hover:text-primary">
                            {name}
                        </CardTitle>
                    </Link>
                    <CardDescription className="mt-1 flex items-center gap-1">
                        {city ? (
                            <>
                                <MapPin aria-hidden="true" />
                                <span className="truncate">{city}</span>
                            </>
                        ) : (
                            'Negocio en Honduras'
                        )}
                    </CardDescription>
                </div>
            </CardHeader>

            <CardContent className="flex flex-1 flex-col gap-3 px-5 pb-4 pt-0">
                    {category ? (
                        <div>
                            <Badge variant="outline">{category}</Badge>
                        </div>
                    ) : null}

                    {tenant?.description ? (
                        <p className="line-clamp-2 text-sm leading-6 text-muted-foreground">
                            {tenant.description}
                        </p>
                    ) : (
                        <p className="text-sm leading-6 text-muted-foreground">
                            Revisa su informacion, productos y formas de contacto.
                        </p>
                    )}
            </CardContent>

            <CardFooter className="justify-between px-5 pb-5 pt-0 text-sm font-medium text-primary">
                <Link href={href} className="inline-flex items-center gap-2" data-testid="primary-tenant-cta">
                    <span>Ver negocio</span>
                    <ArrowRight
                        className={cn('transition-transform group-hover:translate-x-0.5')}
                        data-icon="inline-end"
                        aria-hidden="true"
                    />
                </Link>
            </CardFooter>
        </Card>
    );
}
