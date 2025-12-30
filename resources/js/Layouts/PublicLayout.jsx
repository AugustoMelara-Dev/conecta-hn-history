/**
 * Layouts/PublicLayout.jsx - SEO-Optimized Public Shell
 *
 * FEATURES:
 * - Semantic HTML structure (<header>, <main>, <footer>)
 * - Responsive sticky navbar with hamburger menu
 * - Lightweight - no admin scripts
 * - Flash notification support
 *
 * @param {Object} props
 * @param {React.ReactNode} props.children - Page content
 */

import { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';

// ═══════════════════════════════════════════════════════════════════════════
// TOAST NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════════════════
function Toast({ message, type = 'success', onClose }) {
    useEffect(() => {
        const timer = setTimeout(onClose, 4000);
        return () => clearTimeout(timer);
    }, [onClose]);

    const colors = {
        success: 'bg-emerald-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
    };

    return (
        <div className={`fixed bottom-6 right-6 z-50 px-6 py-4 rounded-xl text-white shadow-2xl ${colors[type]} animate-slide-up`}>
            <div className="flex items-center gap-3">
                {type === 'success' && (
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                )}
                <span className="font-medium">{message}</span>
                <button onClick={onClose} className="ml-2 opacity-70 hover:opacity-100">×</button>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// NAVBAR
// ═══════════════════════════════════════════════════════════════════════════
function Navbar() {
    const { auth } = usePage().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');

    const handleSearch = (e) => {
        e.preventDefault();
        if (searchQuery.trim()) {
            window.location.href = route('directory.index', { search: searchQuery });
        }
    };

    return (
        <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-gray-100">
            <nav className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between h-16">
                    {/* Logo */}
                    <Link href="/" className="flex items-center gap-2 shrink-0">
                        <div className="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center">
                            <span className="text-white font-bold text-lg">C</span>
                        </div>
                        <span className="text-xl font-bold text-gray-900 hidden sm:block">
                            Conecta<span className="text-blue-600">HN</span>
                        </span>
                    </Link>

                    {/* Search Bar (Desktop) */}
                    <form onSubmit={handleSearch} className="hidden md:flex flex-1 max-w-md mx-8">
                        <div className="relative w-full">
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Buscar negocios..."
                                className="w-full pl-12 pr-4 py-2.5 bg-gray-100 border-0 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                            />
                            <svg className="w-5 h-5 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </form>

                    {/* Auth Buttons (Desktop) */}
                    <div className="hidden md:flex items-center gap-3">
                        {auth?.user ? (
                            <Link
                                href={route('tenant.dashboard')}
                                className="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors"
                            >
                                Mi Panel
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium"
                                >
                                    Iniciar Sesión
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors"
                                >
                                    Registrarse
                                </Link>
                            </>
                        )}
                    </div>

                    {/* Hamburger Menu (Mobile) */}
                    <button
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                        className="md:hidden p-2 text-gray-600 hover:text-gray-900"
                        aria-label="Toggle menu"
                    >
                        {mobileMenuOpen ? (
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        ) : (
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        )}
                    </button>
                </div>

                {/* Mobile Menu */}
                {mobileMenuOpen && (
                    <div className="md:hidden py-4 border-t border-gray-100">
                        {/* Mobile Search */}
                        <form onSubmit={handleSearch} className="mb-4">
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Buscar negocios..."
                                className="w-full px-4 py-3 bg-gray-100 rounded-xl text-sm"
                            />
                        </form>

                        {/* Mobile Links */}
                        <div className="flex flex-col gap-2">
                            <Link href={route('directory.index')} className="px-4 py-3 text-gray-700 hover:bg-gray-50 rounded-xl">
                                Directorio
                            </Link>
                            {auth?.user ? (
                                <Link href={route('tenant.dashboard')} className="px-4 py-3 bg-blue-600 text-white text-center rounded-xl font-semibold">
                                    Mi Panel
                                </Link>
                            ) : (
                                <>
                                    <Link href={route('login')} className="px-4 py-3 text-gray-700 hover:bg-gray-50 rounded-xl">
                                        Iniciar Sesión
                                    </Link>
                                    <Link href={route('register')} className="px-4 py-3 bg-blue-600 text-white text-center rounded-xl font-semibold">
                                        Registrarse
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                )}
            </nav>
        </header>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// FOOTER
// ═══════════════════════════════════════════════════════════════════════════
function Footer() {
    return (
        <footer className="bg-gray-900 text-white mt-auto">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
                    {/* Brand */}
                    <div className="md:col-span-2">
                        <div className="flex items-center gap-2 mb-4">
                            <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-xl flex items-center justify-center">
                                <span className="text-white font-bold">C</span>
                            </div>
                            <span className="text-xl font-bold">ConectaHN</span>
                        </div>
                        <p className="text-gray-400 max-w-sm">
                            El directorio más completo de negocios en Honduras. Conecta con servicios locales de confianza.
                        </p>
                    </div>

                    {/* Links */}
                    <div>
                        <h4 className="font-semibold mb-4">Explorar</h4>
                        <ul className="space-y-2 text-gray-400">
                            <li><Link href={route('directory.index')} className="hover:text-white">Directorio</Link></li>
                            <li><Link href={route('directory.index', { category: 'food' })} className="hover:text-white">Restaurantes</Link></li>
                            <li><Link href={route('directory.index', { category: 'services' })} className="hover:text-white">Servicios</Link></li>
                        </ul>
                    </div>

                    {/* Legal */}
                    <div>
                        <h4 className="font-semibold mb-4">Legal</h4>
                        <ul className="space-y-2 text-gray-400">
                            <li><a href="/privacy" className="hover:text-white">Privacidad</a></li>
                            <li><a href="/terms" className="hover:text-white">Términos</a></li>
                            <li><a href="/contact" className="hover:text-white">Contacto</a></li>
                        </ul>
                    </div>
                </div>

                <div className="border-t border-gray-800 mt-8 pt-8 text-center text-gray-500 text-sm">
                    © {new Date().getFullYear()} ConectaHN. Todos los derechos reservados.
                </div>
            </div>
        </footer>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN LAYOUT
// ═══════════════════════════════════════════════════════════════════════════
export default function PublicLayout({ children }) {
    const { flash } = usePage().props;
    const [toast, setToast] = useState(null);

    useEffect(() => {
        if (flash?.success) setToast({ message: flash.success, type: 'success' });
        if (flash?.error) setToast({ message: flash.error, type: 'error' });
    }, [flash]);

    return (
        <div className="min-h-screen flex flex-col bg-gray-50">
            <Navbar />,
            <main className="flex-1">
                {children}
            </main>
            <Footer />

            {/* Toast */}
            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}

            {/* Animation Styles */}
            <style>{`
                @keyframes slide-up {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .animate-slide-up { animation: slide-up 0.3s ease-out; }
            `}</style>
        </div>
    );
}
