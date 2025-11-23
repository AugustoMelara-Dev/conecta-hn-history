import { Link, Head } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { ArrowRight, Building2, Users, ShieldCheck, Zap, Globe, BarChart3, Check, X, Search, Layout, MapPin } from 'lucide-react';
import BlurImage from '@/Components/Molecules/BlurImage';

export default function Welcome({ auth, featuredTenants = [] }) {
    return (
        <>
            <Head title="Conecta HN - La Plataforma Digital de Honduras" />

            <div className="min-h-screen bg-white font-sans text-slate-900 selection:bg-slate-900 selection:text-white">
                {/* 1. Navbar: Sticky & Minimal */}
                <nav className="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-xl border-b border-slate-100">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="w-8 h-8 bg-slate-900 rounded-lg flex items-center justify-center">
                                <span className="text-white font-bold text-lg">C</span>
                            </div>
                            <span className="font-bold text-xl tracking-tight">Conecta HN</span>
                        </div>

                        <div className="flex items-center gap-4">
                            {auth.user ? (
                                <Link href={auth.user.role === 'seller' ? '/app' : '/mi-cuenta'}>
                                    <Button className="rounded-full bg-slate-900 hover:bg-slate-800 text-white px-6 shadow-lg shadow-slate-900/20">
                                        {auth.user.role === 'seller' ? 'Ir al Panel' : 'Mi Cuenta'}
                                    </Button>
                                </Link>
                            ) : (
                                <>
                                    <Link href={route('login')}>
                                        <Button variant="ghost" className="text-slate-600 hover:text-slate-900 font-medium">
                                            Iniciar Sesión
                                        </Button>
                                    </Link>
                                    <Link href={route('register')}>
                                        <Button className="rounded-full bg-slate-900 hover:bg-slate-800 text-white px-6 shadow-lg shadow-slate-900/20">
                                            Registrarse
                                        </Button>
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </nav>

                {/* 2. Hero Section: SaaS Style */}
                <section className="relative pt-32 pb-32 overflow-hidden">
                    <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-50 border border-slate-200 mb-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
                            <span className="flex h-2 w-2 rounded-full bg-black"></span>
                            <span className="text-xs font-medium text-slate-600 uppercase tracking-wide">Ecosistema Digital</span>
                        </div>

                        <h1 className="text-5xl md:text-7xl font-bold tracking-tight text-slate-900 mb-8 leading-[1.1] animate-in fade-in slide-in-from-bottom-6 duration-700 delay-100">
                            Infraestructura Digital <br className="hidden md:block" />
                            <span className="text-slate-900">de Honduras.</span>
                        </h1>

                        <p className="text-xl text-slate-600 max-w-2xl mx-auto mb-12 leading-relaxed animate-in fade-in slide-in-from-bottom-8 duration-700 delay-200">
                            La plataforma centralizada para conectar negocios legítimos con consumidores exigentes. Sin ruido, solo resultados.
                        </p>

                        <div className="flex flex-col sm:flex-row items-center justify-center gap-4 animate-in fade-in slide-in-from-bottom-10 duration-700 delay-300">
                            <Link href={route('register')}>
                                <Button className="h-14 px-8 rounded-full bg-black hover:bg-slate-800 text-white text-lg shadow-xl shadow-black/10 w-full sm:w-auto transition-all hover:scale-105">
                                    <Building2 className="mr-2 h-5 w-5" />
                                    Registrar Negocio
                                </Button>
                            </Link>
                            <Link href={route('directory.index')}>
                                <Button variant="outline" className="h-14 px-8 rounded-full border-slate-200 text-slate-900 hover:bg-slate-50 text-lg w-full sm:w-auto transition-all hover:scale-105">
                                    <Search className="mr-2 h-5 w-5" />
                                    Buscar en Directorio
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Background Gradients - Subtle & Grey */}
                    <div className="absolute top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
                        <div className="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-slate-100 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob"></div>
                        <div className="absolute top-1/3 right-1/4 w-[500px] h-[500px] bg-slate-50 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-blob animation-delay-2000"></div>
                    </div>
                </section>

                {/* 3. Vision & Segmentation */}
                <section className="py-24 bg-white border-y border-slate-100">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16 max-w-3xl mx-auto">
                            <h2 className="text-3xl font-bold text-slate-900 mb-6">Nuestra Visión</h2>
                            <p className="text-lg text-slate-600 leading-relaxed">
                                Transformar la economía local mediante tecnología de clase mundial.
                                Eliminamos las barreras entre el comercio tradicional y el digital,
                                ofreciendo herramientas potentes para vendedores y una experiencia impecable para compradores.
                            </p>
                        </div>

                        <div className="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                            {/* Business Card */}
                            <div className="group relative bg-slate-50 p-10 rounded-3xl border border-slate-200 hover:border-slate-300 transition-all hover:shadow-lg">
                                <div className="w-14 h-14 bg-black rounded-2xl flex items-center justify-center mb-8 group-hover:scale-110 transition-transform">
                                    <Building2 className="w-7 h-7 text-white" />
                                </div>
                                <h3 className="text-2xl font-bold text-slate-900 mb-4">Para Negocios</h3>
                                <p className="text-slate-600 mb-8 leading-relaxed">
                                    Digitaliza tu operación en minutos. Obtén un micrositio verificado,
                                    gestiona tu inventario y recibe pedidos directos a WhatsApp.
                                </p>
                                <ul className="space-y-3 mb-8">
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-900" /> Micrositio SEO-Optimizado
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-900" /> Panel de Gestión Total
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-900" /> Sin Comisiones por Venta
                                    </li>
                                </ul>
                                <Link href={route('register')}>
                                    <Button className="w-full bg-white border border-slate-200 text-slate-900 hover:bg-slate-100 rounded-full h-12 font-medium">
                                        Empezar como Negocio
                                    </Button>
                                </Link>
                            </div>

                            {/* Buyer Card */}
                            <div className="group relative bg-slate-50 p-10 rounded-3xl border border-slate-200 hover:border-slate-300 transition-all hover:shadow-lg">
                                <div className="w-14 h-14 bg-white border border-slate-200 rounded-2xl flex items-center justify-center mb-8 group-hover:scale-110 transition-transform">
                                    <Users className="w-7 h-7 text-slate-900" />
                                </div>
                                <h3 className="text-2xl font-bold text-slate-900 mb-4">Para Compradores</h3>
                                <p className="text-slate-600 mb-8 leading-relaxed">
                                    Descubre lo mejor de tu ciudad. Accede a un directorio curado
                                    de negocios verificados, compara precios y contacta con seguridad.
                                </p>
                                <ul className="space-y-3 mb-8">
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-400" /> Negocios Verificados
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-400" /> Búsqueda Inteligente
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-400" /> Contacto Directo
                                    </li>
                                </ul>
                                <Link href={route('register')}>
                                    <Button className="w-full bg-slate-900 text-white hover:bg-slate-800 rounded-full h-12 font-medium">
                                        Crear Cuenta Personal
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* 3.5. Featured Tenants - Pilar 7: BlurImage Integration */}
                {featuredTenants.length > 0 && (
                    <section className="py-24 bg-slate-50">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div className="text-center mb-12">
                                <h2 className="text-3xl font-bold text-slate-900 mb-4">Negocios Destacados</h2>
                                <p className="text-lg text-slate-600 max-w-2xl mx-auto">
                                    Comercios confiables listos para atenderte
                                </p>
                            </div>

                            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                                {featuredTenants.map((tenant) => (
                                    <Link
                                        key={tenant.id}
                                        href={route('tenant.show', tenant.slug)}
                                        className="group flex flex-col items-center"
                                    >
                                        <div className="w-full aspect-square bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 mb-3 relative">
                                            {tenant.logo_path ? (
                                                <BlurImage
                                                    src={`/storage/${tenant.logo_path}`}
                                                    blurhash={tenant.blur_hash}
                                                    alt={tenant.name}
                                                    className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                                />
                                            ) : (
                                                <div className="w-full h-full bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center">
                                                    <span className="text-2xl font-bold text-slate-400">
                                                        {tenant.name.substring(0, 2).toUpperCase()}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                        <h3 className="text-sm font-semibold text-slate-900 text-center line-clamp-1 group-hover:text-blue-600 transition-colors">
                                            {tenant.name}
                                        </h3>
                                        {tenant.city && (
                                            <p className="text-xs text-slate-500 flex items-center gap-1 mt-1">
                                                <MapPin className="w-3 h-3" />
                                                {tenant.city.name}
                                            </p>
                                        )}
                                    </Link>
                                ))}
                            </div>

                            <div className="text-center mt-12">
                                <Link href={route('directory.index')}>
                                    <Button variant="outline" className="rounded-full h-12 px-8">
                                        <Search className="mr-2 h-4 w-4" />
                                        Ver Todos los Negocios
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </section>
                )}

                {/* 4. Benefits Table: Free vs Pro */}
                <section className="py-24 bg-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl font-bold text-slate-900">Planes Transparentes</h2>
                            <p className="mt-4 text-lg text-slate-600">Sin costos ocultos. Elige cómo quieres crecer.</p>
                        </div>

                        <div className="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                            {/* Free Plan */}
                            <div className="bg-white rounded-3xl p-8 border border-slate-200 shadow-sm">
                                <div className="mb-8">
                                    <h3 className="text-2xl font-bold text-slate-900 mb-2">Plan Gratuito</h3>
                                    <p className="text-slate-500">Visibilidad esencial.</p>
                                    <div className="mt-6 text-4xl font-bold text-slate-900">L 0 <span className="text-lg font-normal text-slate-500">/mes</span></div>
                                </div>
                                <ul className="space-y-4 mb-8">
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-900" /> Perfil de Negocio Básico
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-900" /> Hasta 10 Productos
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-900" /> Presencia en Directorio
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-400">
                                        <X className="w-5 h-5" /> Sin Botón de WhatsApp Directo
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-400">
                                        <X className="w-5 h-5" /> Sin Insignia de Verificación
                                    </li>
                                </ul>
                                <Link href={route('register')}>
                                    <Button variant="outline" className="w-full border-slate-200 hover:bg-slate-50 text-slate-900 rounded-full h-12">
                                        Comenzar Gratis
                                    </Button>
                                </Link>
                            </div>

                            {/* Pro Plan */}
                            <div className="bg-black text-white rounded-3xl p-8 border border-slate-800 relative overflow-hidden shadow-2xl shadow-black/20">
                                <div className="absolute top-0 right-0 bg-white text-black text-xs font-bold px-3 py-1 rounded-bl-xl">
                                    RECOMENDADO
                                </div>
                                <div className="mb-8">
                                    <h3 className="text-2xl font-bold mb-2">Plan PRO</h3>
                                    <p className="text-slate-400">Potencia total.</p>
                                    <div className="mt-6 text-4xl font-bold">L 499 <span className="text-lg font-normal text-slate-500">/mes</span></div>
                                </div>
                                <ul className="space-y-4 mb-8">
                                    <li className="flex items-center gap-3 text-slate-300">
                                        <div className="p-1 bg-white/10 rounded-full"><Check className="w-4 h-4 text-white" /></div>
                                        <strong>Perfil Verificado (Check)</strong>
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-300">
                                        <div className="p-1 bg-white/10 rounded-full"><Check className="w-4 h-4 text-white" /></div>
                                        Productos Ilimitados
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-300">
                                        <div className="p-1 bg-white/10 rounded-full"><Check className="w-4 h-4 text-white" /></div>
                                        Botón de WhatsApp Directo
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-300">
                                        <div className="p-1 bg-white/10 rounded-full"><Check className="w-4 h-4 text-white" /></div>
                                        Posicionamiento Prioritario
                                    </li>
                                </ul>
                                <Link href={route('register')}>
                                    <Button className="w-full bg-white text-black hover:bg-slate-200 rounded-full h-12 font-bold border-none">
                                        Obtener PRO
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* 5. Footer */}
                <footer className="bg-slate-50 border-t border-slate-200 pt-16 pb-8">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12">
                            <div className="col-span-2 md:col-span-1">
                                <div className="flex items-center gap-2 mb-4">
                                    <div className="w-6 h-6 bg-slate-900 rounded flex items-center justify-center">
                                        <span className="text-white font-bold text-xs">C</span>
                                    </div>
                                    <span className="font-bold text-lg tracking-tight">Conecta HN</span>
                                </div>
                                <p className="text-sm text-slate-500 leading-relaxed mb-4">
                                    Impulsando la economía digital de Honduras. Conectamos negocios con oportunidades.
                                </p>
                            </div>

                            <div>
                                <h4 className="font-bold text-slate-900 mb-4">Plataforma</h4>
                                <ul className="space-y-2 text-sm text-slate-600">
                                    <li><Link href={route('directory.index')} className="hover:text-slate-900">Directorio</Link></li>
                                    <li><Link href={route('register')} className="hover:text-slate-900">Registrar Negocio</Link></li>
                                    <li><Link href={route('login')} className="hover:text-slate-900">Iniciar Sesión</Link></li>
                                </ul>
                            </div>

                            <div>
                                <h4 className="font-bold text-slate-900 mb-4">Legal</h4>
                                <ul className="space-y-2 text-sm text-slate-600">
                                    <li><a href="#" className="hover:text-slate-900">Términos de Uso</a></li>
                                    <li><a href="#" className="hover:text-slate-900">Privacidad</a></li>
                                    <li><a href="#" className="hover:text-slate-900">Cookies</a></li>
                                </ul>
                            </div>

                            <div>
                                <h4 className="font-bold text-slate-900 mb-4">Contacto</h4>
                                <ul className="space-y-2 text-sm text-slate-600">
                                    <li>soporte@conectahn.com</li>
                                    <li>Tegucigalpa, Honduras</li>
                                </ul>
                            </div>
                        </div>

                        <div className="border-t border-slate-200 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
                            <p className="text-sm text-slate-400">
                                © {new Date().getFullYear()} Conecta HN. Todos los derechos reservados.
                            </p>
                            <div className="flex gap-4">
                                <span className="w-2 h-2 rounded-full bg-green-500"></span>
                                <span className="text-xs font-medium text-slate-500">Sistemas Operativos</span>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
