import { Head, router } from '@inertiajs/react';
import { Heart, Search } from 'lucide-react';

import EmptyState from '@/Components/Marketing/EmptyState';
import TenantCard from '@/Components/Public/TenantCard';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import PublicLayout from '@/Layouts/PublicLayout';

export default function FavoritesIndex({ tenants = [], total = 0 }) {
    const items = Array.isArray(tenants) ? tenants : [];

    return (
        <PublicLayout>
            <Head title="Mis favoritos" />

            <section className="border-b bg-muted/25">
                <div className="mx-auto flex max-w-7xl flex-col gap-5 px-4 py-10 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-center gap-3">
                        <Badge variant="secondary">
                            <Heart data-icon="inline-start" aria-hidden="true" />
                            Guardados
                        </Badge>
                        <span className="text-sm tabular-nums text-muted-foreground">
                            {total} {total === 1 ? 'negocio' : 'negocios'}
                        </span>
                    </div>
                    <div className="max-w-2xl">
                        <h1 className="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                            Mis favoritos
                        </h1>
                        <p className="mt-3 text-pretty text-base leading-7 text-muted-foreground">
                            Guarda negocios para volver a su catálogo, servicios, contacto y reservas sin repetir la búsqueda.
                        </p>
                    </div>
                </div>
            </section>

            <section className="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {items.length > 0 ? (
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" data-testid="favorites-grid">
                        {items.map((tenant) => (
                            <TenantCard
                                key={tenant.id}
                                tenant={{ ...tenant, is_favorited: true }}
                                onFavoriteToggled={() => router.reload({ only: ['tenants', 'total'] })}
                            />
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={Heart}
                        title="Aún no guardaste negocios"
                        description="Explora el directorio y usa el corazón para construir una lista útil para ti."
                        action={{
                            label: 'Explorar directorio',
                            onClick: () => router.visit('/directorio'),
                        }}
                    />
                )}

                {items.length > 0 ? (
                    <div className="mt-8 flex justify-center">
                        <Button variant="outline" onClick={() => router.visit('/directorio')}>
                            <Search data-icon="inline-start" aria-hidden="true" />
                            Buscar más negocios
                        </Button>
                    </div>
                ) : null}
            </section>
        </PublicLayout>
    );
}
