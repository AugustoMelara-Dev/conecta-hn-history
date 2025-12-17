import { Head, Link } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';
import ProBusinessPage from './Templates/ProBusinessPage';
import FreeBusinessPage from './Templates/FreeBusinessPage';

export default function TenantShow({ tenant, products, isFollowing, followersCount, seoStructuredData, isOpen, openingHours }) {
    // 1. Validación de Existencia
    if (!tenant) return <div className="flex items-center justify-center min-h-screen">Cargando tienda...</div>;

    // 2. Safe Accessors
    const safeProducts = products || tenant.products || [];
    const safeColors = tenant.primary_color || '#000000';
    const isPro = tenant?.is_pro || false;

    return (
        <div style={{ '--primary': safeColors }}>
            <Head>
                <title>{tenant?.name || 'Tenant'} - Conecta HN</title>
                <meta name="description" content={tenant?.description || `Descubre ${tenant?.name || 'este negocio'} en Conecta HN.`} />
                {seoStructuredData && (
                    <script type="application/ld+json">
                        {JSON.stringify(seoStructuredData)}
                    </script>
                )}
            </Head>

            {/* Navbar Overlay for Navigation Back */}
            <nav className="fixed top-0 left-0 right-0 z-50 pointer-events-none">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <Link
                        href={route('directory.index')}
                        className="pointer-events-auto flex items-center gap-2 text-white/80 hover:text-white bg-black/20 hover:bg-black/40 backdrop-blur-md px-3 py-1.5 rounded-full transition-all text-sm font-medium mt-4"
                    >
                        ← Volver
                    </Link>

                    {/* Brand Logo only visible on Free pages or if scrolled (handled by templates usually, but keeping here as fallback) */}
                    {!isPro && (
                        <Link href="/" className="pointer-events-auto mt-4">
                            <div className="w-8 h-8 bg-slate-900 rounded-lg flex items-center justify-center shadow-lg">
                                <span className="text-white font-bold text-sm">C</span>
                            </div>
                        </Link>
                    )}
                </div>
            </nav>

            {/* Template Switcher */}
            {isPro ? (
                <ProBusinessPage
                    tenant={tenant}
                    products={safeProducts}
                    isOpen={isOpen}
                    openingHours={openingHours}
                />
            ) : (
                <FreeBusinessPage
                    tenant={tenant}
                    products={safeProducts}
                    isOpen={isOpen}
                />
            )}
        </div>
    );
}
