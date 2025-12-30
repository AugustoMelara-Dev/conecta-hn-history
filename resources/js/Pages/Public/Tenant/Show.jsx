/**
 * Pages/Public/Tenant/Show.jsx - Business Microsite
 *
 * FEATURES:
 * - Hero with cover image and logo
 * - Open/Closed status based on schedule
 * - WhatsApp FAB (mobile)
 * - Product showcase with detail modal
 * - SEO optimized
 *
 * PROPS (from TenantController@show):
 * @param {Object} props.tenant - Public tenant data
 * @param {Array} props.products - Public product list
 * @param {boolean} props.isOpen - Current open status
 * @param {Object|null} props.todaySchedule - { open, close } or null
 */

import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

// ═══════════════════════════════════════════════════════════════════════════
// FORMATTERS
// ═══════════════════════════════════════════════════════════════════════════
const currencyFormatter = new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
    minimumFractionDigits: 0,
});

// ═══════════════════════════════════════════════════════════════════════════
// HERO SECTION
// ═══════════════════════════════════════════════════════════════════════════
function HeroSection({ tenant, isOpen, todaySchedule }) {
    const placeholderCover = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="1200" height="400"%3E%3Crect fill="%234f46e5" width="1200" height="400"/%3E%3C/svg%3E';

    return (
        <section className="relative">
            {/* Cover Image */}
            <div className="h-48 sm:h-64 lg:h-80 bg-gray-200 overflow-hidden">
                <img
                    src={tenant.cover_url || placeholderCover}
                    alt={`${tenant.name} cover`}
                    className="w-full h-full object-cover"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
            </div>

            {/* Content Overlay */}
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="relative -mt-20 sm:-mt-24 pb-6">
                    <div className="flex flex-col sm:flex-row sm:items-end gap-4">
                        {/* Logo */}
                        <div className="w-28 h-28 sm:w-36 sm:h-36 rounded-2xl border-4 border-white shadow-xl overflow-hidden bg-white shrink-0">
                            {tenant.logo_url ? (
                                <img src={tenant.logo_url} alt={tenant.name} className="w-full h-full object-cover" />
                            ) : (
                                <div className="w-full h-full bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center">
                                    <span className="text-white text-4xl font-bold">{tenant.name?.[0]}</span>
                                </div>
                            )}
                        </div>

                        {/* Info */}
                        <div className="flex-1 text-white sm:pb-2">
                            <div className="flex items-center gap-3 mb-2">
                                <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 sm:text-white drop-shadow-lg">
                                    {tenant.name}
                                </h1>
                                <span className={`px-3 py-1 rounded-full text-sm font-bold ${isOpen ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-600'
                                    }`}>
                                    {isOpen ? 'Abierto' : 'Cerrado'}
                                </span>
                            </div>
                            {tenant.category && (
                                <span className="inline-block px-3 py-1 bg-white/20 backdrop-blur text-white rounded-full text-sm">
                                    {tenant.category}
                                </span>
                            )}
                            {todaySchedule && (
                                <p className="text-sm text-gray-300 mt-2">
                                    Hoy: {todaySchedule.open} - {todaySchedule.close}
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// CONTACT ACTIONS
// ═══════════════════════════════════════════════════════════════════════════
function ContactActions({ tenant }) {
    const whatsappUrl = tenant.whatsapp_number
        ? `https://wa.me/${tenant.whatsapp_number.replace(/\D/g, '')}?text=Hola, vi tu perfil en ConectaHN`
        : null;

    const mapsUrl = tenant.address
        ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(tenant.address)}`
        : null;

    return (
        <div className="bg-white rounded-2xl border border-gray-100 p-6 sticky top-24">
            <h3 className="font-bold text-lg mb-4">Contacto</h3>

            <div className="space-y-3">
                {/* WhatsApp */}
                {whatsappUrl && (
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-3 w-full px-4 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold rounded-xl transition-colors"
                    >
                        <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                        </svg>
                        WhatsApp
                    </a>
                )}

                {/* Phone */}
                {tenant.phone && (
                    <a
                        href={`tel:${tenant.phone}`}
                        className="flex items-center gap-3 w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        Llamar
                    </a>
                )}

                {/* Directions */}
                {mapsUrl && (
                    <a
                        href={mapsUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-3 w-full px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-colors"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Cómo Llegar
                    </a>
                )}
            </div>

            {/* Address */}
            {tenant.address && (
                <p className="text-sm text-gray-500 mt-4 pt-4 border-t border-gray-100">
                    📍 {tenant.address}
                </p>
            )}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// PRODUCT MODAL
// ═══════════════════════════════════════════════════════════════════════════
function ProductModal({ product, onClose }) {
    if (!product) return null;

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-gray-500/75" onClick={onClose} />
            <div className="flex min-h-full items-center justify-center p-4">
                <div className="relative bg-white rounded-2xl shadow-xl max-w-lg w-full overflow-hidden">
                    {/* Image */}
                    {product.image_url && (
                        <img
                            src={product.image_url}
                            alt={product.name}
                            className="w-full h-64 object-cover"
                        />
                    )}

                    {/* Content */}
                    <div className="p-6">
                        <div className="flex items-start justify-between mb-4">
                            <h3 className="text-2xl font-bold text-gray-900">{product.name}</h3>
                            <span className="text-xl font-bold text-blue-600">
                                {currencyFormatter.format(product.price)}
                            </span>
                        </div>

                        {product.description && (
                            <p className="text-gray-600 mb-6">{product.description}</p>
                        )}

                        <button
                            onClick={onClose}
                            className="w-full py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-colors"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// PRODUCT GRID
// ═══════════════════════════════════════════════════════════════════════════
function ProductGrid({ products }) {
    const [selectedProduct, setSelectedProduct] = useState(null);

    if (!products?.length) return null;

    return (
        <section className="py-8">
            <h2 className="text-xl font-bold text-gray-900 mb-6">Productos</h2>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                {products.map((product) => (
                    <button
                        key={product.id}
                        onClick={() => setSelectedProduct(product)}
                        className="text-left bg-white rounded-xl border border-gray-100 overflow-hidden hover:shadow-lg transition-all group"
                    >
                        {/* Image */}
                        <div className="aspect-square bg-gray-100 overflow-hidden">
                            {product.image_url ? (
                                <img
                                    src={product.image_url}
                                    alt={product.name}
                                    loading="lazy"
                                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />
                            ) : (
                                <div className="w-full h-full flex items-center justify-center text-gray-400">
                                    <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            )}
                        </div>

                        {/* Info */}
                        <div className="p-3">
                            <h3 className="font-semibold text-gray-900 text-sm line-clamp-1">{product.name}</h3>
                            <p className="text-blue-600 font-bold mt-1">
                                {currencyFormatter.format(product.price)}
                            </p>
                        </div>
                    </button>
                ))}
            </div>

            <ProductModal product={selectedProduct} onClose={() => setSelectedProduct(null)} />
        </section>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// FLOATING ACTION BUTTON (Mobile WhatsApp)
// ═══════════════════════════════════════════════════════════════════════════
function WhatsAppFAB({ tenant }) {
    if (!tenant.whatsapp_number) return null;

    const whatsappUrl = `https://wa.me/${tenant.whatsapp_number.replace(/\D/g, '')}?text=Hola, vi tu perfil en ConectaHN`;

    return (
        <a
            href={whatsappUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="lg:hidden fixed bottom-6 right-6 z-50 w-16 h-16 bg-emerald-500 hover:bg-emerald-600 rounded-full shadow-2xl flex items-center justify-center"
        >
            <svg className="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
            </svg>
        </a>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE
// ═══════════════════════════════════════════════════════════════════════════
export default function TenantShow({ tenant, products = [], isOpen = false, todaySchedule = null }) {
    return (
        <PublicLayout>
            <Head>
                <title>{tenant.name} - ConectaHN</title>
                <meta name="description" content={tenant.description || `${tenant.name} en Honduras. ${tenant.category || ''} ${tenant.city || ''}`} />
                <meta property="og:title" content={tenant.name} />
                <meta property="og:description" content={tenant.description || ''} />
                {tenant.cover_url && <meta property="og:image" content={tenant.cover_url} />}
            </Head>

            <div className="min-h-screen bg-gray-50">
                <HeroSection tenant={tenant} isOpen={isOpen} todaySchedule={todaySchedule} />

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="flex flex-col lg:flex-row gap-8">
                        {/* Main Content */}
                        <div className="flex-1">
                            {/* About */}
                            {tenant.description && (
                                <section className="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
                                    <h2 className="font-bold text-lg mb-4">Acerca de</h2>
                                    <p className="text-gray-600 whitespace-pre-line">{tenant.description}</p>
                                </section>
                            )}

                            {/* Products */}
                            <div className="bg-white rounded-2xl border border-gray-100 p-6">
                                <ProductGrid products={products} />
                            </div>
                        </div>

                        {/* Sidebar */}
                        <div className="w-full lg:w-80 shrink-0">
                            <ContactActions tenant={tenant} />
                        </div>
                    </div>
                </div>

                <WhatsAppFAB tenant={tenant} />
            </div>
        </PublicLayout>
    );
}
