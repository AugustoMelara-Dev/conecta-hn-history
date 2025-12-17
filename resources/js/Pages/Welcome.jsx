
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Search,
    ArrowRight,
    Zap,
    Smartphone,
    BarChart3,
    Check,
    Menu,
    X,
    ShoppingBag,
    Star,
    MapPin
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import BlurImage from '@/Components/Molecules/BlurImage';

export default function Welcome({ auth, metrics, categories, featuredTenants }) {
    const [scrolled, setScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    // Search Form
    const { data, setData, get } = useForm({
        search: '',
    });

    const handleSearch = (e) => {
        e.preventDefault();
        get(route('directory.index'));
    };

    // Scroll effect for navbar
    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 50);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const fadeInUp = {
        hidden: { opacity: 0, y: 20 },
        visible: { opacity: 1, y: 0, transition: { duration: 0.6 } }
    };

    const staggerContainer = {
        hidden: { opacity: 0 },
        visible: {
            opacity: 1,
            transition: {
                staggerChildren: 0.1
            }
        }
    };

    return (
        <div className="min-h-screen bg-white font-sans text-slate-900 selection:bg-indigo-500 selection:text-white overflow-x-hidden">
            <Head title="Conecta HN - El Sistema Operativo del Comercio Local" />

            {/* 1. Navbar */}
            <nav className={`fixed top-0 z-50 w-full transition-all duration-300 ${scrolled ? 'bg-white/90 backdrop-blur-md border-b border-slate-100 py-2' : 'bg-transparent py-4'}`}>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Link href="/" className="flex items-center gap-2 group">
                            <div className="w-10 h-10 bg-black rounded-xl flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform">
                                <span className="text-white font-bold text-xl">C</span>
                            </div>
                            <span className={`font-bold text-xl tracking-tight transition-colors ${scrolled ? 'text-slate-900' : 'text-white'}`}>
                                Conecta HN
                            </span>
                        </Link>
                    </div>

                    {/* Desktop Nav */}
                    <div className="hidden md:flex items-center gap-6">
                        <Link
                            href="/directorio"
                            className={`font-medium text-sm transition-colors ${scrolled ? 'text-slate-600 hover:text-black' : 'text-slate-300 hover:text-white'}`}
                        >
                            Directorio
                        </Link>

                        {auth.user ? (
                            <div className="flex items-center gap-4">
                                <span className={`text-sm font-medium ${scrolled ? 'text-slate-900' : 'text-white'}`}>
                                    Hola, {auth.user.name.split(' ')[0]}
                                </span>
                                <Link href={auth.user.role === 'seller' ? '/admin' : '/directorio'}>
                                    <Button className="rounded-full bg-indigo-600 hover:bg-indigo-700 text-white border-0 shadow-lg shadow-indigo-500/30">
                                        {auth.user.role === 'seller' ? 'Mi Negocio' : 'Mi Cuenta'}
                                    </Button>
                                </Link>
                            </div>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className={`font-medium text-sm transition-colors ${scrolled ? 'text-slate-600 hover:text-black' : 'text-slate-300 hover:text-white'}`}
                                >
                                    Iniciar Sesión
                                </Link>
                                <Link href={route('register')}>
                                    <Button className="rounded-full bg-indigo-600 hover:bg-indigo-700 text-white border-0 shadow-lg shadow-indigo-500/30">
                                        Crear tu Tienda
                                    </Button>
                                </Link>
                            </>
                        )}
                    </div>

                    {/* Mobile Toggle */}
                    <div className="md:hidden">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className={scrolled ? 'text-slate-900' : 'text-white'}
                        >
                            {mobileMenuOpen ? <X /> : <Menu />}
                        </Button>
                    </div>
                </div>

                {/* Mobile Menu */}
                <AnimatePresence>
                    {mobileMenuOpen && (
                        <motion.div
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            className="md:hidden bg-white border-b border-slate-100 overflow-hidden"
                        >
                            <div className="p-4 space-y-4 flex flex-col items-center">
                                <Link href="/directorio" className="text-slate-600 font-medium">Directorio</Link>

                                {auth.user ? (
                                    <>
                                        <span className="text-slate-900 font-bold">Hola, {auth.user.name}</span>
                                        <Link href={auth.user.role === 'seller' ? '/admin' : '/directorio'} className="w-full">
                                            <Button className="w-full bg-indigo-600">
                                                {auth.user.role === 'seller' ? 'Ir a mi Panel' : 'Mi Cuenta'}
                                            </Button>
                                        </Link>
                                    </>
                                ) : (
                                    <>
                                        <Link href={route('login')} className="text-slate-600 font-medium">Login</Link>
                                        <Link href={route('register')} className="w-full">
                                            <Button className="w-full bg-indigo-600">Crear Cuenta</Button>
                                        </Link>
                                    </>
                                )}
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </nav>

            {/* 2. Hero Section (B2C Focus) */}
            <section className="relative h-[90vh] min-h-[600px] flex items-center justify-center bg-[#0a0a0a] overflow-hidden">

                {/* Aurora Borealis Background Effects */}
                <div className="absolute top-[-20%] left-[-10%] w-[500px] h-[500px] bg-purple-600/30 rounded-full blur-[120px] animate-pulse" />
                <div className="absolute top-[20%] right-[-10%] w-[400px] h-[400px] bg-indigo-600/20 rounded-full blur-[100px]" />
                <div className="absolute bottom-[-10%] left-[20%] w-[600px] h-[600px] bg-blue-600/10 rounded-full blur-[150px]" />

                {/* Hero Content */}
                <div className="relative z-10 w-full max-w-4xl px-4 text-center">
                    <motion.div
                        initial="hidden"
                        animate="visible"
                        variants={staggerContainer}
                        className="space-y-8"
                    >
                        <motion.div variants={fadeInUp}>
                            <Badge variant="outline" className="mb-4 text-indigo-400 border-indigo-500/30 bg-indigo-500/10 px-4 py-1.5 text-sm uppercase tracking-wider backdrop-blur-sm">
                                Nuevo en Conecta HN v2.0
                            </Badge>
                            <h1 className="text-5xl md:text-7xl font-extrabold tracking-tight text-white mb-6 leading-tight">
                                El Sistema Operativo del <br />
                                <span className="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-purple-400">
                                    Comercio Local
                                </span>
                            </h1>
                            <p className="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto">
                                Descubre, pide y conecta con los mejores negocios de Honduras.
                                Sin intermediarios, sin comisiones ocultas.
                            </p>
                        </motion.div>

                        {/* Search Bar Visual */}
                        <motion.div variants={fadeInUp} className="max-w-2xl mx-auto relative group">
                            <div className="absolute -inset-1 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full blur opacity-25 group-hover:opacity-75 transition duration-1000 group-hover:duration-200"></div>
                            <form onSubmit={handleSearch} className="relative flex items-center">
                                <Search className="absolute left-6 text-slate-400 w-6 h-6" />
                                <input
                                    type="text"
                                    value={data.search}
                                    onChange={(e) => setData('search', e.target.value)}
                                    placeholder="¿Qué se te antoja hoy? (Sushi, Pizza, Regalos...)"
                                    className="w-full pl-16 pr-6 py-5 rounded-full bg-white/10 backdrop-blur-md border border-white/10 text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 text-lg shadow-2xl transition-all"
                                />
                                <Button
                                    type="submit"
                                    className="absolute right-2 top-2 bottom-2 rounded-full bg-indigo-600 hover:bg-indigo-700 px-8 text-base font-semibold"
                                >
                                    Buscar
                                </Button>
                            </form>
                        </motion.div>

                        {/* Quick Categories Pills */}
                        <motion.div variants={fadeInUp} className="flex flex-wrap justify-center gap-3 pt-4">
                            {categories.map((cat) => (
                                <Link
                                    key={cat.id}
                                    href={`/directorio?category=${cat.slug}`}
                                    className="px-4 py-2 rounded-full bg-white/5 border border-white/10 text-slate-300 text-sm hover:bg-white/10 hover:text-white hover:border-white/20 transition-all flex items-center gap-2 cursor-pointer backdrop-blur-sm"
                                >
                                    <span>{cat.icon || '🔍'}</span>
                                    {cat.name}
                                </Link>
                            ))}
                        </motion.div>
                    </motion.div>
                </div>

                {/* Floating Mockup (Optional nice touch for bottom) */}
                <div className="absolute bottom-0 w-full h-32 bg-gradient-to-t from-white to-transparent md:h-64" />
            </section>

            {/* 3. Social Proof Section */}
            <section className="bg-white border-b border-slate-100 py-10 relative z-20 -mt-10 rounded-t-[3rem]">
                <div className="max-w-7xl mx-auto px-4 text-center">
                    <p className="text-slate-500 font-medium mb-8">
                        Impulsando a más de <strong className="text-slate-900">{metrics.total_tenants}+ emprendedores</strong> en todo el país
                    </p>

                    {/* Hero Tenants Grid (Mini showcase) */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                        {featuredTenants.map((tenant, idx) => (
                            <Link key={tenant.id} href={`/${tenant.slug}`} className="group relative block aspect-video rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all">
                                <BlurImage
                                    src={tenant.cover}
                                    blurHash={tenant.blur_hash}
                                    alt={tenant.name}
                                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent flex flex-col justify-end p-6 text-left">
                                    <div className="flex items-center gap-3 mb-2">
                                        <div className="bg-white p-1 rounded-full w-10 h-10 flex-shrink-0">
                                            <img src={tenant.logo} alt={tenant.name} className="w-full h-full rounded-full object-cover" />
                                        </div>
                                        <div>
                                            <h3 className="text-white font-bold truncate">{tenant.name}</h3>
                                            <div className="flex items-center text-xs text-slate-300 gap-1">
                                                <MapPin className="w-3 h-3" />
                                                {tenant.city}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>

                    <div className="mt-8 text-center">
                        <Link href="/directorio" className="text-indigo-600 font-semibold hover:text-indigo-800 flex items-center justify-center gap-2 group">
                            Explorar todo el directorio <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                        </Link>
                    </div>
                </div>
            </section>

            {/* 4. Feature Section (B2B Pitch) */}
            <section className="py-24 bg-slate-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                        <motion.div
                            initial={{ opacity: 0, x: -30 }}
                            whileInView={{ opacity: 1, x: 0 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.8 }}
                        >
                            <h2 className="text-4xl font-extrabold text-slate-900 mb-6 leading-tight">
                                Tu propio sitio web profesional en <span className="text-indigo-600">60 segundos</span>.
                            </h2>
                            <p className="text-lg text-slate-600 mb-8 leading-relaxed">
                                Deja de luchar con chats desordenados y excel manuales. Conecta HN te da todo lo que necesitas para vender como los grandes, gratis.
                            </p>

                            <div className="space-y-6">
                                {[
                                    { icon: Zap, title: "Botón Mágico de WhatsApp", desc: "Tus clientes llenan el carrito y el pedido llega formateado a tu chat. Cero errores." },
                                    { icon: Smartphone, title: "Catálogo Online (Micrositio)", desc: "Tu propia URL (conectahn.com/tu-marca) para compartir en Instagram y Bio." },
                                    { icon: BarChart3, title: "Control Total", desc: "Revisa visitas, productos más vistos y optimiza tus ventas con datos reales." }
                                ].map((feature, idx) => (
                                    <div key={idx} className="flex gap-4 p-4 rounded-2xl bg-white border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                                        <div className="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600 flex-shrink-0">
                                            <feature.icon className="w-6 h-6" />
                                        </div>
                                        <div>
                                            <h4 className="font-bold text-slate-900">{feature.title}</h4>
                                            <p className="text-sm text-slate-500 mt-1">{feature.desc}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="mt-10">
                                <Link href={route('register')}>
                                    <Button size="lg" className="bg-black hover:bg-slate-800 text-white rounded-full px-8 h-12 text-base">
                                        Comenzar Gratis
                                    </Button>
                                </Link>
                            </div>
                        </motion.div>

                        <motion.div
                            initial={{ opacity: 0, scale: 0.95 }}
                            whileInView={{ opacity: 1, scale: 1 }}
                            viewport={{ once: true }}
                            transition={{ duration: 0.8 }}
                            className="relative"
                        >
                            {/* Abstract decorative blobs */}
                            <div className="absolute top-10 right-10 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-2xl opacity-30 animate-blob"></div>
                            <div className="absolute -bottom-8 -left-8 w-72 h-72 bg-indigo-300 rounded-full mix-blend-multiply filter blur-2xl opacity-30 animate-blob animation-delay-2000"></div>

                            {/* Device Mockup */}
                            <div className="relative rounded-3xl border-8 border-slate-900 bg-slate-900 shadow-2xl overflow-hidden aspect-[9/19] max-w-sm mx-auto transform rotate-[-2deg] hover:rotate-0 transition-transform duration-500">
                                <div className="absolute top-0 w-full h-full bg-white overflow-hidden">
                                    {/* Mock Interface */}
                                    <div className="h-full w-full bg-slate-50 flex flex-col">
                                        <div className="h-40 bg-indigo-600 relative">
                                            <div className="absolute bottom-[-24px] left-6 w-16 h-16 bg-white rounded-full border-4 border-white"></div>
                                        </div>
                                        <div className="p-6 pt-10 space-y-4">
                                            <div className="h-4 w-3/4 bg-slate-200 rounded"></div>
                                            <div className="h-3 w-1/2 bg-slate-100 rounded"></div>
                                            <div className="grid grid-cols-2 gap-3 mt-4">
                                                <div className="h-32 bg-white rounded-lg shadow-sm"></div>
                                                <div className="h-32 bg-white rounded-lg shadow-sm"></div>
                                                <div className="h-32 bg-white rounded-lg shadow-sm"></div>
                                                <div className="h-32 bg-white rounded-lg shadow-sm"></div>
                                            </div>
                                        </div>
                                        <div className="mt-auto bg-white p-4 border-t border-slate-100">
                                            <div className="h-10 bg-green-500 rounded-full w-full flex items-center justify-center text-white text-xs font-bold">
                                                Enviar Pedido WhatsApp
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </motion.div>
                    </div>
                </div>
            </section>

            {/* 5. Pricing Section */}
            <section className="py-24 bg-white relative overflow-hidden">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center mb-16">
                    <h2 className="text-3xl md:text-4xl font-extrabold text-slate-900 mb-4">Elige tu nivel de juego</h2>
                    <p className="text-slate-500 max-w-2xl mx-auto">Emprende sin riesgos. Escala cuando estés listo.</p>
                </div>

                <div className="max-w-4xl mx-auto px-4 grid md:grid-cols-2 gap-8 items-center">

                    {/* Basic Plan */}
                    <Card className="border border-slate-200 shadow-sm hover:shadow-lg transition-shadow bg-white relative">
                        <CardHeader>
                            <CardTitle className="text-xl font-bold">Plan Emprendedor</CardTitle>
                            <div className="mt-2 text-4xl font-extrabold">Gratis</div>
                            <p className="text-sm text-slate-500 mt-1">Para siempre</p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <ul className="space-y-3 text-left">
                                <li className="flex items-center gap-2 text-sm text-slate-600"><Check className="w-4 h-4 text-green-500" /> Hasta 10 productos</li>
                                <li className="flex items-center gap-2 text-sm text-slate-600"><Check className="w-4 h-4 text-green-500" /> Catálogo digital básico</li>
                                <li className="flex items-center gap-2 text-sm text-slate-600"><Check className="w-4 h-4 text-green-500" /> Pedidos por WhatsApp</li>
                                <li className="flex items-center gap-2 text-sm text-slate-400"><X className="w-4 h-4" /> Sin Analytics</li>
                                <li className="flex items-center gap-2 text-sm text-slate-400"><X className="w-4 h-4" /> Marca de agua "Conecta HN"</li>
                            </ul>
                        </CardContent>
                        <CardFooter>
                            <Link href={route('register')} className="w-full">
                                <Button variant="outline" className="w-full rounded-full">Crear Cuenta Gratis</Button>
                            </Link>
                        </CardFooter>
                    </Card>

                    {/* Pro Plan */}
                    <Card className="border-2 border-indigo-600 shadow-xl relative scale-105 z-10 bg-white">
                        <div className="absolute top-0 right-0 bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-bl-xl rounded-tr-lg">
                            MÁS POPULAR
                        </div>
                        <CardHeader>
                            <CardTitle className="text-xl font-bold text-indigo-900">Plan PRO</CardTitle>
                            <div className="mt-2 text-4xl font-extrabold text-indigo-600">L. 399<span className="text-lg font-normal text-slate-500">/mes</span></div>
                            <p className="text-sm text-slate-500 mt-1">Conecta con tu audiencia</p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <ul className="space-y-3 text-left">
                                <li className="flex items-center gap-2 text-sm font-medium text-slate-900"><Check className="w-4 h-4 text-indigo-600" /> Productos Ilimitados</li>
                                <li className="flex items-center gap-2 text-sm font-medium text-slate-900"><Check className="w-4 h-4 text-indigo-600" /> Panel de Analíticas Avanzado</li>
                                <li className="flex items-center gap-2 text-sm font-medium text-slate-900"><Check className="w-4 h-4 text-indigo-600" /> Marketing por Email (Campañas)</li>
                                <li className="flex items-center gap-2 text-sm font-medium text-slate-900"><Check className="w-4 h-4 text-indigo-600" /> Sistema de Seguidores</li>
                                <li className="flex items-center gap-2 text-sm font-medium text-slate-900"><Check className="w-4 h-4 text-indigo-600" /> Eliminar Marca de Agua</li>
                            </ul>
                        </CardContent>
                        <CardFooter>
                            <Link href={route('register')} className="w-full">
                                <Button className="w-full rounded-full bg-indigo-600 hover:bg-indigo-700 shadow-lg shadow-indigo-500/25">
                                    Conviértete en PRO
                                </Button>
                            </Link>
                        </CardFooter>
                    </Card>

                </div>
            </section>

            {/* Footer */}
            <footer className="bg-slate-900 text-slate-300 py-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8">
                    <div className="col-span-1 md:col-span-2">
                        <div className="w-10 h-10 bg-white rounded-xl flex items-center justify-center mb-4">
                            <span className="text-slate-900 font-bold text-xl">C</span>
                        </div>
                        <p className="text-sm text-slate-400 max-w-xs">
                            La plataforma que conecta a los mejores negocios de Honduras con sus clientes. Sin límites.
                        </p>
                    </div>
                    <div>
                        <h4 className="font-bold text-white mb-4">Plataforma</h4>
                        <ul className="space-y-2 text-sm">
                            <li><Link href="/directorio" className="hover:text-white">Directorio</Link></li>
                            <li><Link href={route('login')} className="hover:text-white">Login Tiendas</Link></li>
                            <li><Link href={route('register')} className="hover:text-white">Registrar Negocio</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h4 className="font-bold text-white mb-4">Legal</h4>
                        <ul className="space-y-2 text-sm">
                            <li><a href="#" className="hover:text-white">Términos y Condiciones</a></li>
                            <li><a href="#" className="hover:text-white">Privacidad</a></li>
                        </ul>
                    </div>
                </div>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 pt-8 border-t border-slate-800 text-xs text-center text-slate-500">
                    &copy; 2025 Conecta HN. Todos los derechos reservados.
                </div>
            </footer>
        </div>
    );
}
