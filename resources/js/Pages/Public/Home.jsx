/**
 * Pages/Public/Home.jsx - Landing Page
 *
 * FEATURES:
 * - Hero search bar
 * - Category pills (horizontal scroll)
 * - Featured tenants section
 * - SEO optimized with Head component
 *
 * PROPS (from HomeController@index):
 * @param {Array} props.featuredTenants - Top rated/new tenants
 * @param {Array} props.categories - Popular categories
 * @param {Object} props.stats - { total_tenants, total_cities }
 */

import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import TenantCard from '@/Components/Public/TenantCard';

// ═══════════════════════════════════════════════════════════════════════════
// HERO SECTION
// ═══════════════════════════════════════════════════════════════════════════
function HeroSection({ stats }) {
    const [searchQuery, setSearchQuery] = useState('');

    const handleSearch = (e) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            router.get(route('directory.index'), { search: searchQuery });
        }
    };

    return (
        <section className="relative bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-700 text-white overflow-hidden">
            {/* Background Pattern */}
            <div className="absolute inset-0 opacity-10">
                <div className="absolute inset-0" style={{
                    backgroundImage: 'radial-gradient(circle at 2px 2px, white 1px, transparent 0)',
                    backgroundSize: '32px 32px'
                }} />
            </div>

            <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 lg:py-32">
                <div className="max-w-3xl mx-auto text-center">
                    {/* Badge */}
                    <div className="inline-flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur rounded-full text-sm font-medium mb-8">
                        <span className="w-2 h-2 bg-emerald-400 rounded-full animate-pulse" />
                        {stats?.total_tenants || 500}+ negocios registrados
                    </div>

                    {/* Headline */}
                    <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold mb-6 leading-tight">
                        Encuentra el mejor servicio
                        <span className="block text-yellow-300">en Honduras</span>
                    </h1>

                    <p className="text-lg sm:text-xl text-blue-100 mb-10 max-w-xl mx-auto">
                        Conecta con negocios locales de confianza. Restaurantes, servicios, salud y más.
                    </p>

                    {/* Search Bar */}
                    <form onSubmit={handleSearch} className="max-w-2xl mx-auto">
                        <div className="relative flex items-center bg-white rounded-2xl shadow-2xl shadow-black/20 overflow-hidden">
                            <svg className="w-6 h-6 text-gray-400 absolute left-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="¿Qué estás buscando? (ej: Tacos, Mecánico, Dentista)"
                                className="flex-1 pl-14 pr-4 py-5 text-gray-900 text-lg border-0 focus:ring-0 placeholder:text-gray-400"
                            />
                            <button
                                type="submit"
                                className="m-2 px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors"
                            >
                                Buscar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {/* Wave Divider */}
            <div className="absolute bottom-0 left-0 right-0">
                <svg viewBox="0 0 1440 120" fill="none" className="w-full h-auto">
                    <path d="M0 120L60 105C120 90 240 60 360 45C480 30 600 30 720 37.5C840 45 960 60 1080 67.5C1200 75 1320 75 1380 75L1440 75V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="#f9fafb" />
                </svg>
            </div>
        </section>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// CATEGORY PILLS
// ═══════════════════════════════════════════════════════════════════════════
function CategoryPills({ categories }) {
    const defaultCategories = [
        { slug: 'food', name: 'Comida', icon: '🍔' },
        { slug: 'health', name: 'Salud', icon: '🏥' },
        { slug: 'beauty', name: 'Belleza', icon: '💇' },
        { slug: 'automotive', name: 'Autos', icon: '🚗' },
        { slug: 'services', name: 'Servicios', icon: '🔧' },
        { slug: 'shopping', name: 'Tiendas', icon: '🛍️' },
        { slug: 'entertainment', name: 'Entretenimiento', icon: '🎬' },
        { slug: 'education', name: 'Educación', icon: '📚' },
    ];

    const items = categories?.length ? categories : defaultCategories;

    return (
        <section className="py-12 bg-gray-50">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 className="text-2xl font-bold text-gray-900 mb-6 text-center">
                    Explora por Categoría
                </h2>
                <div className="flex gap-3 overflow-x-auto pb-4 scrollbar-hide">
                    {items.map((cat) => (
                        <Link
                            key={cat.slug}
                            href={route('directory.index', { category: cat.slug })}
                            className="flex items-center gap-2 px-5 py-3 bg-white rounded-xl border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all whitespace-nowrap shrink-0"
                        >
                            <span className="text-2xl">{cat.icon}</span>
                            <span className="font-medium text-gray-700">{cat.name}</span>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// FEATURED TENANTS
// ═══════════════════════════════════════════════════════════════════════════
function FeaturedSection({ tenants }) {
    if (!tenants?.length) return null;

    return (
        <section className="py-16 bg-white">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">Destacados</h2>
                        <p className="text-gray-500 mt-1">Los negocios más populares</p>
                    </div>
                    <Link
                        href={route('directory.index')}
                        className="text-blue-600 hover:text-blue-700 font-medium flex items-center gap-1"
                    >
                        Ver todos
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    {tenants.map((tenant) => (
                        <TenantCard key={tenant.id} tenant={tenant} />
                    ))}
                </div>
            </div>
        </section>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// CTA SECTION
// FIX #3: Route corrected to tenant.register (was: register)
// Principle: Conversion Alignment - CTA leads to business registration
// ═══════════════════════════════════════════════════════════════════════════
function CtaSection() {
    return (
        <section className="py-20 bg-gradient-to-r from-indigo-600 to-purple-600">
            <div className="max-w-4xl mx-auto px-4 text-center text-white">
                <h2 className="text-3xl sm:text-4xl font-bold mb-4">
                    ¿Tienes un negocio?
                </h2>
                <p className="text-lg text-indigo-100 mb-8 max-w-xl mx-auto">
                    Únete a ConectaHN y llega a miles de clientes potenciales. Regístrate gratis hoy.
                </p>
                <Link
                    href={route('tenant.register')}
                    className="inline-flex items-center gap-2 px-8 py-4 bg-white text-indigo-600 font-bold rounded-xl hover:bg-gray-100 transition-colors shadow-lg"
                >
                    Crear mi Perfil Gratis
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </Link>
            </div>
        </section>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE
// ═══════════════════════════════════════════════════════════════════════════
export default function Home({ featuredTenants = [], categories = [], stats = {} }) {
    return (
        <PublicLayout>
            <Head>
                <title>ConectaHN - Negocios Locales en Honduras</title>
                <meta name="description" content="Encuentra los mejores negocios locales en Honduras. Restaurantes, servicios, salud, belleza y más. Conecta con empresas de confianza cerca de ti." />
                <meta name="keywords" content="negocios Honduras, directorio empresas, restaurantes Honduras, servicios locales" />
                <link rel="canonical" href={route('home')} />
            </Head>

            <HeroSection stats={stats} />
            <CategoryPills categories={categories} />
            <FeaturedSection tenants={featuredTenants} />
            <CtaSection />
        </PublicLayout>
    );
}
