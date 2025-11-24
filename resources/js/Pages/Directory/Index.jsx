import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import CategoryRail from '@/Components/Organisms/CategoryRail';
import LocationPicker from '@/Components/Organisms/LocationPicker';
import BlurImage from '@/Components/Molecules/BlurImage';
import {
    MapPin, Star, Search, Building2, BadgeCheck, X
} from 'lucide-react';
import { cn } from '@/lib/utils';

export default function DirectoryIndex({ tenants, categories, cities, activeCity, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    // Debounce search
    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (searchTerm !== (filters.search || '')) {
                router.get(
                    route('directory.index'),
                    { ...filters, search: searchTerm },
                    { preserveState: true, preserveScroll: true, replace: true }
                );
            }
        }, 500);

        return () => clearTimeout(delayDebounceFn);
    }, [searchTerm]);

    return (
        <AppLayout>
            <Head title={`Directorio ${activeCity ? `en ${activeCity.name}` : 'de Negocios'}`} />

            <div className="min-h-screen bg-background">
                {/* Main Content */}
                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Category Rail */}
                    <div className="mb-8">
                        <CategoryRail categories={categories} activeCategory={filters.category} />
                    </div>
                    <div className="flex items-center justify-between mb-6">
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            {filters.category
                                ? categories.find(c => c.slug === filters.category)?.name
                                : activeCity
                                    ? `Explora ${activeCity.name}`
                                    : 'Descubre lo mejor de Honduras'}
                        </h1>
                        <span className="text-sm font-medium text-muted-foreground bg-muted px-3 py-1 rounded-full">
                            {tenants.length} resultados
                        </span>
                    </div>

                    {/* Results Grid */}
                    {tenants.length > 0 ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            {tenants.map((tenant) => (
                                <Link
                                    key={tenant.id}
                                    href={route('tenant.show', tenant.slug)}
                                    className="group block h-full"
                                >
                                    <div className={cn(
                                        "bg-card rounded-2xl border transition-all duration-300 overflow-hidden h-full flex flex-col hover:shadow-xl hover:-translate-y-1",
                                        tenant.is_pro ? "border-primary/20 shadow-sm" : "border-border"
                                    )}>
                                        {/* Image */}
                                        <div className="aspect-video relative overflow-hidden bg-muted">
                                            {tenant.banner_path ? (
                                                <BlurImage
                                                    src={`/storage/${tenant.banner_path}`}
                                                    blurhash={tenant.blur_hash}
                                                    alt={tenant.name}
                                                    className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                                                />
                                            ) : (
                                                <div className="w-full h-full flex items-center justify-center bg-gradient-to-br from-muted to-muted/50">
                                                    <Building2 className="w-12 h-12 text-muted-foreground/20" />
                                                </div>
                                            )}

                                            {/* Badges */}
                                            <div className="absolute top-3 right-3 flex gap-2">
                                                {tenant.is_pro && (
                                                    <div className="bg-background/90 backdrop-blur text-foreground text-[10px] font-bold px-2 py-1 rounded-full shadow-sm flex items-center gap-1 border border-border/50">
                                                        <BadgeCheck className="w-3 h-3 text-blue-500 fill-blue-500/10" />
                                                        VERIFICADO
                                                    </div>
                                                )}
                                            </div>

                                            {/* Category Badge */}
                                            <div className="absolute bottom-3 left-3">
                                                <div className="bg-black/60 backdrop-blur text-white text-[10px] font-medium px-2 py-1 rounded-full flex items-center gap-1">
                                                    {tenant.category?.name || 'Negocio'}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Content */}
                                        <div className="p-4 flex-1 flex flex-col">
                                            <div className="flex justify-between items-start gap-2 mb-1">
                                                <h3 className="font-bold text-lg line-clamp-1 group-hover:text-primary transition-colors flex items-center gap-1.5">
                                                    {tenant.name}
                                                    {tenant.is_pro && (
                                                        <BadgeCheck className="w-4 h-4 text-blue-500 fill-blue-500/10 shrink-0" />
                                                    )}
                                                </h3>
                                            </div>

                                            <p className="text-sm text-muted-foreground line-clamp-2 mb-4 flex-1 leading-relaxed">
                                                {tenant.description || 'Sin descripción disponible.'}
                                            </p>

                                            <div className="flex items-center justify-between pt-3 border-t mt-auto">
                                                <div className="flex items-center text-xs text-muted-foreground truncate max-w-[65%]">
                                                    <MapPin className="w-3.5 h-3.5 mr-1.5 shrink-0 text-primary/70" />
                                                    <span className="truncate font-medium">{tenant.city || 'Honduras'}</span>
                                                </div>
                                                <div className="flex items-center gap-1 bg-amber-50 dark:bg-amber-950/30 px-2 py-0.5 rounded-md border border-amber-100 dark:border-amber-900/50">
                                                    <Star className="w-3 h-3 fill-amber-500 text-amber-500" />
                                                    <span className="text-xs font-bold text-amber-700 dark:text-amber-400">4.8</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        /* Empty State */
                        <div className="flex flex-col items-center justify-center py-24 text-center">
                            <div className="w-24 h-24 bg-muted/30 rounded-full flex items-center justify-center mb-6">
                                <Search className="w-10 h-10 text-muted-foreground/50" />
                            </div>
                            <h3 className="text-xl font-bold mb-2 text-foreground">
                                No encontramos resultados
                            </h3>
                            <p className="text-muted-foreground max-w-md mx-auto mb-8">
                                No hay negocios que coincidan con "{searchTerm}" en esta ubicación. Intenta con otra categoría o ciudad.
                            </p>
                            <Button
                                variant="outline"
                                size="lg"
                                onClick={() => {
                                    setSearchTerm('');
                                    router.get(route('directory.index'));
                                }}
                                className="rounded-full px-8"
                            >
                                Limpiar todos los filtros
                            </Button>
                        </div>
                    )}
                </main>
            </div>
        </AppLayout>
    );
}
