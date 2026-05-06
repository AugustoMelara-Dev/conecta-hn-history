/**
 * Pages/Public/Directory/Index.jsx - Directory Search Engine
 *
 * FEATURES:
 * - Filter sidebar/drawer (City, Category, Sort)
 * - Debounced text search (300ms)
 * - Responsive tenant grid
 * - Dynamic SEO meta tags
 * - preserveState navigation
 *
 * PROPS (from DirectoryController@index):
 * @param {Array} props.tenants - Paginated tenant list
 * @param {Object} props.filters - { search, city, category, sort }
 * @param {Array} props.cities - Available cities
 * @param {Array} props.categories - Available categories
 * @param {Object} props.meta - { total, current_page, last_page }
 */

import { useState, useEffect, useCallback } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import TenantCard from '@/Components/Public/TenantCard';

// ═══════════════════════════════════════════════════════════════════════════
// FILTER SIDEBAR
// ═══════════════════════════════════════════════════════════════════════════
function FilterSidebar({ filters, cities, categories, onFilterChange, isMobile = false, onClose }) {
    const [localFilters, setLocalFilters] = useState(filters);

    const handleChange = (key, value) => {
        const newFilters = { ...localFilters, [key]: value };
        setLocalFilters(newFilters);
        onFilterChange(newFilters);
    };

    const clearFilters = () => {
        const cleared = { search: '', city: '', category: '', sort: 'newest' };
        setLocalFilters(cleared);
        onFilterChange(cleared);
    };

    const content = (
        <div className="space-y-6">
            {/* City Filter */}
            <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">Ciudad</label>
                <select
                    value={localFilters.city || ''}
                    onChange={(e) => handleChange('city', e.target.value)}
                    className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Todas las ciudades</option>
                    {cities?.map((city) => (
                        <option key={city.slug} value={city.slug}>{city.name}</option>
                    ))}
                </select>
            </div>

            {/* Category Filter */}
            <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">Categoría</label>
                <div className="space-y-2 max-h-48 overflow-y-auto">
                    {categories?.map((cat) => (
                        <label key={cat.slug} className="flex items-center gap-3 cursor-pointer p-2 hover:bg-gray-50 rounded-lg">
                            <input
                                type="checkbox"
                                checked={localFilters.category === cat.slug}
                                onChange={() => handleChange('category', localFilters.category === cat.slug ? '' : cat.slug)}
                                className="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                            />
                            <span className="text-gray-700">{cat.name}</span>
                        </label>
                    ))}
                </div>
            </div>

            {/* Sort */}
            <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">Ordenar por</label>
                <select
                    value={localFilters.sort || 'newest'}
                    onChange={(e) => handleChange('sort', e.target.value)}
                    className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500"
                >
                    <option value="newest">Más recientes</option>
                    <option value="popular">Más populares</option>
                    <option value="name">Nombre A-Z</option>
                </select>
            </div>

            {/* Clear Button */}
            <button
                onClick={clearFilters}
                className="w-full py-3 text-gray-600 hover:text-gray-900 font-medium border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors"
            >
                Limpiar filtros
            </button>
        </div>
    );

    if (isMobile) {
        return (
            <div className="fixed inset-0 z-50 lg:hidden">
                <div className="absolute inset-0 bg-gray-500/75" onClick={onClose} />
                <div className="absolute right-0 top-0 h-full w-80 max-w-full bg-white shadow-xl">
                    <div className="flex items-center justify-between p-4 border-b">
                        <h3 className="font-bold text-lg">Filtros</h3>
                        <button onClick={onClose} className="p-2 text-gray-400 hover:text-gray-600">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div className="p-4">{content}</div>
                </div>
            </div>
        );
    }

    return (
        <aside className="hidden lg:block w-72 shrink-0">
            <div className="sticky top-24 bg-white rounded-2xl border border-gray-100 p-6">
                <h3 className="font-bold text-lg mb-6">Filtros</h3>
                {content}
            </div>
        </aside>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE
// ═══════════════════════════════════════════════════════════════════════════
export default function DirectoryIndex({
    tenants = [],
    filters = {},
    cities = [],
    categories = [],
    meta = {},
}) {
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [showMobileFilters, setShowMobileFilters] = useState(false);

    // Debounced search
    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchQuery !== (filters.search || '')) {
                router.get(
                    route('directory.index'),
                    { ...filters, search: searchQuery },
                    { preserveState: true, preserveScroll: true, replace: true }
                );
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [searchQuery]);

    // Handle filter changes
    const handleFilterChange = useCallback((newFilters) => {
        router.get(
            route('directory.index'),
            { ...newFilters },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, []);

    // Dynamic page title
    const pageTitle = filters.city
        ? `Negocios en ${filters.city} - ConectaHN`
        : filters.category
            ? `${filters.category} en Honduras - ConectaHN`
            : 'Directorio de Negocios - ConectaHN';

    return (
        <PublicLayout>
            <Head>
                <title>{pageTitle}</title>
                <meta name="description" content={`Encuentra los mejores negocios${filters.city ? ` en ${filters.city}` : ''}${filters.category ? ` de ${filters.category}` : ''} en Honduras.`} />
            </Head>

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <div className="bg-white border-b border-gray-200 sticky top-16 z-30">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                        <div className="flex items-center gap-4">
                            {/* Search Input */}
                            <div className="flex-1 relative">
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Buscar negocios..."
                                    className="w-full pl-12 pr-4 py-3 bg-gray-100 border-0 rounded-xl focus:ring-2 focus:ring-blue-500 focus:bg-white"
                                />
                                <svg className="w-5 h-5 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>

                            {/* Mobile Filter Toggle */}
                            <button
                                onClick={() => setShowMobileFilters(true)}
                                className="lg:hidden flex items-center gap-2 px-4 py-3 bg-gray-100 rounded-xl font-medium"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                </svg>
                                Filtros
                            </button>
                        </div>

                        {/* Results Count */}
                        <p className="text-sm text-gray-500 mt-3">
                            {meta.total || tenants.length} resultados
                            {filters.city && ` en ${filters.city}`}
                        </p>
                    </div>
                </div>

                {/* Content */}
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="flex gap-8">
                        {/* Desktop Sidebar */}
                        <FilterSidebar
                            filters={filters}
                            cities={cities}
                            categories={categories}
                            onFilterChange={handleFilterChange}
                        />

                        {/* Results Grid */}
                        <div className="flex-1">
                            {tenants.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                                    {tenants.map((tenant) => (
                                        <TenantCard key={tenant.id} tenant={tenant} />
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-16 bg-white rounded-2xl border border-gray-100">
                                    <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                        <svg className="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-xl font-bold text-gray-900 mb-2">Sin resultados</h3>
                                    <p className="text-gray-500 max-w-sm mx-auto">
                                        No encontramos negocios con esos criterios. Intenta con otros filtros.
                                    </p>
                                </div>
                            )}

                            {/* Pagination */}
                            {meta.last_page > 1 && (
                                <div className="mt-8 flex justify-center gap-2">
                                    {Array.from({ length: meta.last_page }, (_, i) => i + 1).map((page) => (
                                        <Link
                                            key={page}
                                            href={route('directory.index', { ...filters, page })}
                                            className={`w-10 h-10 flex items-center justify-center rounded-xl font-medium ${page === meta.current_page
                                                    ? 'bg-blue-600 text-white'
                                                    : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'
                                                }`}
                                        >
                                            {page}
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Mobile Filter Drawer */}
                {showMobileFilters && (
                    <FilterSidebar
                        filters={filters}
                        cities={cities}
                        categories={categories}
                        onFilterChange={handleFilterChange}
                        isMobile={true}
                        onClose={() => setShowMobileFilters(false)}
                    />
                )}
            </div>
        </PublicLayout>
    );
}
