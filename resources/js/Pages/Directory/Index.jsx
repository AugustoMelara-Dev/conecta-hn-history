import { useState, useEffect, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import CategoryRail from '@/Components/Organisms/CategoryRail';
import BlurImage from '@/Components/Molecules/BlurImage';
import {
    MapPin, Star, Search, Building2, BadgeCheck, X, Filter, ChevronDown
} from 'lucide-react';
import { cn } from '@/lib/utils';

// ═══════════════════════════════════════════════════════════════════════════
// UTILITY: Clean empty params from URL
// ═══════════════════════════════════════════════════════════════════════════
function cleanParams(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '')
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// COMPONENT: TenantCard - Individual Business Card
// ═══════════════════════════════════════════════════════════════════════════
function TenantCard({ tenant }) {
    return (
        <Link
            href={route('tenant.show', tenant.slug)}
            className="group block h-full"
        >
            <div className={cn(
                "bg-card rounded-2xl border transition-all duration-300 overflow-hidden h-full flex flex-col hover:shadow-xl hover:-translate-y-1",
                tenant.is_pro ? "border-primary/20 shadow-sm" : "border-border"
            )}>
                {/* Image with lazy loading */}
                <div className="aspect-video relative overflow-hidden bg-muted">
                    {tenant.banner_path ? (
                        <BlurImage
                            src={`/storage/${tenant.banner_path}`}
                            blurhash={tenant.blur_hash}
                            alt={tenant.name}
                            loading="lazy"
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

                    <p className="text-sm text-muted-foreground line-clamp-2 mb-3 flex-1 leading-relaxed">
                        {tenant.description || 'Sin descripción disponible.'}
                    </p>

                    {/* Tags Pills (max 3) */}
                    {tenant.tags?.length > 0 && (
                        <div className="flex flex-wrap gap-1.5 mb-3">
                            {tenant.tags.slice(0, 3).map((tag, idx) => (
                                <span
                                    key={idx}
                                    className="text-[10px] font-medium px-2 py-0.5 bg-muted text-muted-foreground rounded-full"
                                >
                                    {tag}
                                </span>
                            ))}
                        </div>
                    )}

                    <div className="flex items-center justify-between pt-3 border-t mt-auto">
                        <div className="flex items-center text-xs text-muted-foreground truncate max-w-[65%]">
                            <MapPin className="w-3.5 h-3.5 mr-1.5 shrink-0 text-primary/70" />
                            <span className="truncate font-medium">{tenant.city?.name || 'Honduras'}</span>
                        </div>
                        <div className="flex items-center gap-1 bg-amber-50 dark:bg-amber-950/30 px-2 py-0.5 rounded-md border border-amber-100 dark:border-amber-900/50">
                            <Star className="w-3 h-3 fill-amber-500 text-amber-500" />
                            <span className="text-xs font-bold text-amber-700 dark:text-amber-400">4.8</span>
                        </div>
                    </div>
                </div>
            </div>
        </Link>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// COMPONENT: FilterPanel - Desktop & Mobile Filter Controls
// ═══════════════════════════════════════════════════════════════════════════
function FilterPanel({ cities, filters, onFilterChange, className = '' }) {
    return (
        <div className={cn("space-y-4", className)}>
            {/* City Dropdown */}
            <div>
                <label className="block text-sm font-medium mb-2 text-foreground">Ciudad</label>
                <div className="relative">
                    <select
                        value={filters.city || ''}
                        onChange={(e) => onFilterChange('city', e.target.value)}
                        className="w-full h-10 px-3 pr-8 rounded-lg border border-input bg-background text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="">Todas las ciudades</option>
                        {cities.map((city) => (
                            <option key={city.id} value={city.slug}>{city.name}</option>
                        ))}
                    </select>
                    <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                </div>
            </div>

            {/* Clear Filters */}
            {(filters.search || filters.city || filters.category) && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onFilterChange('clear', null)}
                    className="w-full"
                >
                    <X className="w-4 h-4 mr-2" />
                    Limpiar filtros
                </Button>
            )}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function DirectoryIndex({ tenants, categories, cities, activeCity, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);

    // ═══════════════════════════════════════════════════════════════════════
    // FILTER HANDLER: Single source of truth for all filter changes
    // ═══════════════════════════════════════════════════════════════════════
    const applyFilters = useCallback((newFilters) => {
        const cleanedParams = cleanParams(newFilters);

        router.get(
            route('directory.index'),
            cleanedParams,
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, []);

    const handleFilterChange = useCallback((key, value) => {
        if (key === 'clear') {
            setSearchTerm('');
            applyFilters({});
            return;
        }

        const newFilters = { ...filters, [key]: value || null };

        // If changing search, update local state
        if (key === 'search') {
            setSearchTerm(value);
        }

        applyFilters(newFilters);
        setMobileFiltersOpen(false);
    }, [filters, applyFilters]);

    // ═══════════════════════════════════════════════════════════════════════
    // DEBOUNCED SEARCH: 500ms delay to prevent API spam
    // ═══════════════════════════════════════════════════════════════════════
    useEffect(() => {
        const handler = setTimeout(() => {
            if (searchTerm !== (filters.search || '')) {
                applyFilters({ ...filters, search: searchTerm || null });
            }
        }, 500);

        return () => clearTimeout(handler);
    }, [searchTerm, filters, applyFilters]); // Full dependency array prevents stale closures

    return (
        <AppLayout>
            <Head title={`Directorio ${activeCity ? `en ${activeCity.name}` : 'de Negocios'}`} />

            <div className="min-h-screen bg-background">
                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

                    {/* Category Rail */}
                    <div className="mb-8">
                        <CategoryRail
                            categories={[{ id: 'all', name: 'Ver Todos', slug: '', icon: '🔍' }, ...categories]}
                            activeCategory={filters.category}
                        />
                    </div>

                    {/* Filters Bar */}
                    <div className="flex flex-col lg:flex-row gap-4 mb-6">
                        {/* Search Input */}
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Buscar negocios, productos, servicios..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-10 h-11"
                            />
                        </div>

                        {/* Desktop City Filter */}
                        <div className="hidden lg:block w-64">
                            <div className="relative">
                                <select
                                    value={filters.city || ''}
                                    onChange={(e) => handleFilterChange('city', e.target.value)}
                                    className="w-full h-11 px-3 pr-8 rounded-lg border border-input bg-background text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="">Todas las ciudades</option>
                                    {cities.map((city) => (
                                        <option key={city.id} value={city.slug}>{city.name}</option>
                                    ))}
                                </select>
                                <MapPin className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
                            </div>
                        </div>

                        {/* Mobile Filter Button */}
                        <Button
                            variant="outline"
                            onClick={() => setMobileFiltersOpen(true)}
                            className="lg:hidden"
                        >
                            <Filter className="w-4 h-4 mr-2" />
                            Filtrar
                        </Button>
                    </div>

                    {/* Results Header */}
                    <div className="flex items-center justify-between mb-6">
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            {filters.category
                                ? categories.find(c => c.slug === filters.category)?.name
                                : activeCity
                                    ? `Explora ${activeCity.name}`
                                    : 'Descubre lo mejor de Honduras'}
                        </h1>
                        <span className="text-sm font-medium text-muted-foreground bg-muted px-3 py-1 rounded-full">
                            {tenants.data?.length || 0} resultados
                        </span>
                    </div>

                    {/* Results Grid */}
                    {(tenants.data || tenants).length > 0 ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            {(tenants.data || tenants).map((tenant) => (
                                <TenantCard key={tenant.id} tenant={tenant} />
                            ))}
                        </div>
                    ) : (
                        /* Empty State */
                        <div className="flex flex-col items-center justify-center py-24 text-center">
                            <div className="w-24 h-24 bg-muted/30 rounded-full flex items-center justify-center mb-6">
                                <Search className="w-10 h-10 text-muted-foreground/50" />
                            </div>
                            <h3 className="text-xl font-bold mb-2 text-foreground">
                                {filters.search
                                    ? `No encontramos "${filters.search}"`
                                    : 'No hay negocios disponibles'}
                            </h3>
                            <p className="text-muted-foreground max-w-md mx-auto mb-8">
                                {filters.search
                                    ? `No hay negocios que coincidan con tu búsqueda. ¿Conoces un negocio de "${filters.search}"? ¡Invítalos a registrarse!`
                                    : 'No hay negocios que coincidan con tus filtros. Intenta limpiar los filtros para ver todo el catálogo.'}
                            </p>

                            <div className="flex flex-col sm:flex-row gap-4">
                                <Button
                                    variant="outline"
                                    size="lg"
                                    onClick={() => handleFilterChange('clear', null)}
                                    className="rounded-full px-8"
                                >
                                    <X className="w-4 h-4 mr-2" />
                                    Limpiar filtros
                                </Button>

                                <Link
                                    href={route('register')}
                                    className="inline-flex items-center justify-center gap-2 px-8 h-11 rounded-full bg-primary text-primary-foreground font-semibold hover:bg-primary/90 transition-colors"
                                >
                                    <Building2 className="w-4 h-4" />
                                    Registrar mi negocio
                                </Link>
                            </div>
                        </div>
                    )}

                    {/* Pagination */}
                    {tenants.links && tenants.data?.length > 0 && (
                        <div className="flex justify-center gap-2 mt-12">
                            {tenants.links.map((link, index) => {
                                // Sanitize label: replace HTML entities with readable text
                                const label = link.label
                                    .replace('&laquo;', '«')
                                    .replace('&raquo;', '»')
                                    .replace('Previous', 'Anterior')
                                    .replace('Next', 'Siguiente');

                                return (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={cn(
                                            "px-4 py-2 rounded-lg text-sm font-medium transition-colors",
                                            link.active
                                                ? "bg-primary text-primary-foreground"
                                                : "bg-muted hover:bg-muted/80 text-foreground",
                                            !link.url && "opacity-50 pointer-events-none"
                                        )}
                                    >
                                        {label}
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </main>
            </div>

            {/* Mobile Filter Drawer */}
            {mobileFiltersOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div
                        className="fixed inset-0 bg-black/50"
                        onClick={() => setMobileFiltersOpen(false)}
                    />
                    <div className="fixed inset-y-0 right-0 w-full max-w-xs bg-background shadow-xl">
                        <div className="flex items-center justify-between p-4 border-b">
                            <h2 className="font-bold text-lg">Filtros</h2>
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => setMobileFiltersOpen(false)}
                            >
                                <X className="w-5 h-5" />
                            </Button>
                        </div>
                        <div className="p-4">
                            <FilterPanel
                                cities={cities}
                                filters={filters}
                                onFilterChange={handleFilterChange}
                            />
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
