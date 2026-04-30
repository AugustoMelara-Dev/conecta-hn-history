/**
 * Components/Public/TenantCard.jsx - Business Card for Directory
 *
 * FEATURES:
 * - Lazy loaded cover image
 * - Logo avatar overlay
 * - Category badge
 * - Entire card is clickable link
 *
 * @param {Object} props
 * @param {Object} props.tenant - Tenant object
 * @param {number} props.tenant.id
 * @param {string} props.tenant.name
 * @param {string} props.tenant.slug
 * @param {string|null} props.tenant.cover_url
 * @param {string|null} props.tenant.logo_url
 * @param {string|null} props.tenant.category
 * @param {string|null} props.tenant.city
 * @param {string|null} props.tenant.description
 * @param {boolean} props.tenant.is_open - Current open status
 */

import { Link } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';

export default function TenantCard({ tenant }) {
    // FIX #6: Defensive programming - prevent crash if tenant.name is undefined
    // Principle: Nullish Coalescing for fail-safe rendering
    const firstLetter = tenant?.name?.[0]?.toUpperCase() || '?';

    // Placeholder image for missing covers
    const placeholderCover = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="200"%3E%3Crect fill="%23e5e7eb" width="400" height="200"/%3E%3C/svg%3E';
    const placeholderLogo = `data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="80" height="80"%3E%3Crect fill="%236366f1" width="80" height="80" rx="12"/%3E%3Ctext x="40" y="50" text-anchor="middle" fill="white" font-size="32" font-weight="bold"%3E${firstLetter}%3C/text%3E%3C/svg%3E`;

    return (
        <Card className="group overflow-hidden transition-shadow hover:shadow-lg">
            <Link
                href={route('tenant.microsite.show', { tenant: tenant.slug })}
                className="block focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <div className="relative aspect-[16/9] overflow-hidden bg-muted">
                    <img
                        src={tenant.cover_url || placeholderCover}
                        alt={`Portada de ${tenant.name}`}
                        loading="lazy"
                        className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                    />

                    {typeof tenant.is_open === 'boolean' && (
                        <Badge
                            variant={tenant.is_open ? 'default' : 'secondary'}
                            className="absolute right-3 top-3"
                        >
                            {tenant.is_open ? 'Abierto' : 'Cerrado'}
                        </Badge>
                    )}

                    <div className="absolute -bottom-8 left-4">
                        <div className="size-16 overflow-hidden rounded-xl border-4 border-background bg-background shadow-lg">
                            <img
                                src={tenant.logo_url || placeholderLogo}
                                alt={`Logo de ${tenant.name}`}
                                loading="lazy"
                                className="h-full w-full object-cover"
                            />
                        </div>
                    </div>
                </div>

                <CardContent className="flex flex-col gap-3 px-5 pb-5 pt-10">
                    <div className="min-w-0">
                        <h3 className="truncate text-lg font-bold text-foreground transition-colors group-hover:text-primary">
                            {tenant.name}
                        </h3>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        {tenant.city && (
                            <span className="inline-flex min-w-0 items-center gap-1">
                                <svg className="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span className="truncate">{tenant.city}</span>
                            </span>
                        )}
                        {tenant.category && (
                            <Badge variant="secondary" className="max-w-full truncate">
                                {tenant.category}
                            </Badge>
                        )}
                    </div>

                    {tenant.description && (
                        <p className="line-clamp-2 text-sm text-muted-foreground">
                            {tenant.description}
                        </p>
                    )}
                </CardContent>
            </Link>
        </Card>
    );
}
