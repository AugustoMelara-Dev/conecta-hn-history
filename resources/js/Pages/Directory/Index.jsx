import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import FilterBar from '@/Components/Organisms/FilterBar';
import LocationPicker from '@/Components/Organisms/LocationPicker';
import BlurImage from '@/Components/Molecules/BlurImage';
import {
    MapPin, Star, Search, Building2, BadgeCheck, ArrowLeftRight, MapPinOff
} from 'lucide-react';
import { cn } from '@/lib/utils';

export default function DirectoryIndex({ tenants, categories, cities, activeCity, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            route('directory.index'),
            { ...filters, search: searchTerm },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    const clearCityFilter = () => {
        router.post(
            route('location.clear'),
            {},
            { preserveScroll: true }
        );
    };

    return (
        <AppLayout>
            <Head title={`Directorio ${activeCity ? `en ${activeCity.name}` : 'de Negocios'}`} />

            {/* Horizontal Filter Bar */}
            <FilterBar categories={categories} />

            <div className="py-6">
                {/* Header Section */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <div>
                        <h1 className="text-3xl font-bold">
                            Explora {activeCity ? activeCity.name : 'Honduras'}
                        </h1>
                        <p className="text-muted-foreground">
                            {tenants.length} negocios encontrados
                        </p>
                    </div>

                    <LocationPicker cities={cities} activeCity={activeCity} />
                </div>

                {/* Search Bar */}
                <form onSubmit={handleSearch} className="mb-8">
                    <div className="relative max-w-md">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            type="search"
                            placeholder="Buscar negocios..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="pl-10"
                        />
                    </div>
                </form>

                {/* Results Section */}
                {tenants.length > 0 ? (
                    <div className="space-y-12">
                        {/* Group tenants by category */}
                        {Object.entries(
                            tenants.reduce((acc, tenant) => {
                                const categoryName = tenant.category?.name || 'Otros';
                                if (!acc[categoryName]) acc[categoryName] = [];
                                acc[categoryName].push(tenant);
                                return acc;
                            }, {})
                        ).map(([categoryName, categoryTenants]) => (
                            <div key={categoryName}>
                                <h2 className="text-xl font-bold mb-6 flex items-center gap-2">
                                    {categoryName}
                                    <span className="text-sm font-normal text-muted-foreground">
                                        ({categoryTenants.length})
                                    </span>
                                </h2>

                                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                    {categoryTenants.map((tenant) => (
                                        <Link
                                            key={tenant.id}
                                            href={route('tenant.show', tenant.slug)}
                                            className="group block"
                                        >
                                            <div
                                                className={cn(
                                                    'bg-card rounded-xl border transition-all duration-300 overflow-hidden h-full flex flex-col hover:shadow-lg',
                                                    tenant.is_pro
                                                        ? 'border-amber-200 ring-1 ring-amber-100'
                                                        : 'border-border hover:border-muted-foreground'
                                                )}
                                            >
                                                {/* Banner Image */}
                                                <div className="aspect-video bg-muted relative overflow-hidden">
                                                    {tenant.banner_path ? (
                                                        <BlurImage
                                                            src={`/storage/${tenant.banner_path}`}
                                                            blurhash={tenant.blur_hash}
                                                            alt={tenant.name}
                                                            className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                                                        />
                                                    ) : (
                                                        <div className="w-full h-full bg-gradient-to-br from-muted to-muted-foreground/10 flex items-center justify-center">
                                                            <Building2 className="w-12 h-12 text-muted-foreground/30" />
                                                        </div>
                                                    )}

                                                    {/* Logo Overlay */}
                                                    <div className="absolute -bottom-6 left-4 w-12 h-12 bg-background rounded-full border-2 border-background shadow-md overflow-hidden flex items-center justify-center z-10">
                                                        {tenant.logo_path ? (
                                                            <BlurImage
                                                                src={`/storage/${tenant.logo_path}`}
                                                                blurhash={tenant.blur_hash}
                                                                alt="Logo"
                                                                className="w-full h-full object-cover"
                                                            />
                                                        ) : (
                                                            <span className="font-bold text-xs">
                                                                {tenant.name.substring(0, 2).toUpperCase()}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>

                                                {/* Content */}
                                                <div className="p-5 pt-8 flex-1 flex flex-col">
                                                    <div className="flex justify-between items-start mb-2">
                                                        <h3 className="font-bold text-lg line-clamp-1 group-hover:text-primary transition-colors flex items-center gap-1.5">
                                                            {tenant.name}
                                                            {tenant.is_pro && (
                                                                <BadgeCheck className="w-5 h-5 text-blue-500 fill-blue-50" />
                                                            )}
                                                        </h3>
                                                    </div>

                                                    <p className="text-muted-foreground text-sm mb-4 line-clamp-2 flex-1">
                                                        {tenant.description || 'Sin descripción disponible.'}
                                                    </p>

                                                    {/* Rating */}
                                                    <div className="flex items-center gap-1 mb-4">
                                                        <Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />
                                                        <span className="text-sm font-bold">4.8</span>
                                                        <span className="text-sm text-muted-foreground">(12)</span>
                                                    </div>

                                                    {/* Footer */}
                                                    <div className="pt-4 border-t flex items-center justify-between mt-auto">
                                                        <Badge variant="outline" className="text-xs">
                                                            {tenant.category?.name || 'General'}
                                                        </Badge>

                                                        {tenant.is_pro && (
                                                            <Badge className="bg-amber-500/10 text-amber-700 hover:bg-amber-500/20">
                                                                PRO
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    /* Empty State - Pilar 7: Enhanced UX */
                    <div className="text-center py-20 bg-muted/30 rounded-xl border-2 border-dashed">
                        <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-muted mb-4">
                            <MapPinOff className="w-8 h-8 text-muted-foreground" />
                        </div>
                        <h3 className="text-lg font-bold mb-2">
                            No hay negocios en {activeCity ? activeCity.name : 'esta ubicación'}
                        </h3>
                        <p className="text-muted-foreground max-w-md mx-auto mb-6">
                            {activeCity
                                ? 'Intenta explorar otras ciudades o categorías.'
                                : 'Selecciona una ciudad para ver negocios disponibles.'}
                        </p>
                        {activeCity && (
                            <Button variant="outline" onClick={clearCityFilter}>
                                Ver en todas las ciudades
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
