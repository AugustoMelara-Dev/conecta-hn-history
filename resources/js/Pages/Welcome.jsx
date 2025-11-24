import { Link, Head } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/Components/ui/accordion';
import {
    Rocket,
    Play,
    TrendingDown,
    Clock,
    AlertTriangle,
    Zap,
    MessageCircle,
    Palette,
    Check,
    Menu,
    X,
    Instagram,
    Facebook,
    Twitter
} from 'lucide-react';
import BlurImage from '@/Components/Molecules/BlurImage';
import { useState } from 'react';

export default function Welcome({ auth, featuredTenants = [] }) {
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

    return (
        <>
            <Head title="Crea tu Sitio Web Profesional Gratis - Conecta HN" />

            <div className="min-h-screen bg-white font-sans text-slate-900 selection:bg-slate-900 selection:text-white">

                {/* 1. Navbar */}
                <nav className="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-100">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="w-8 h-8 bg-black rounded-lg flex items-center justify-center">
                                <span className="text-white font-bold text-lg">C</span>
                            </div>
                            <span className="font-bold text-xl tracking-tight">Conecta HN</span>
                        </div>

                        {/* Desktop Nav */}
                        <div className="hidden md:flex items-center gap-4">
                            {auth.user ? (
                                <Link href={auth.user.role === 'seller' ? '/app' : '/mi-cuenta'}>
                                    <Button className="rounded-full bg-black hover:bg-slate-800 text-white px-6">
                                        {auth.user.role === 'seller' ? 'Ir al Panel' : 'Mi Cuenta'}
                                    </Button>
                                </Link>
                            ) : (
                                <>
                                    <Link href={route('login')}>
                                        <Button variant="ghost" className="text-slate-600 hover:text-slate-900">
                                            Iniciar Sesión
                                        </Button>
                                    </Link>
                                    <Link href={route('register')}>
                                        <Button className="rounded-full bg-black hover:bg-slate-800 text-white px-6">
                                            Empezar Gratis
                                        </Button>
                                    </Link>
                                </>
                            )}
                        </div>

                        {/* Mobile Menu Toggle */}
                        <button
                            className="md:hidden p-2 text-slate-600"
                            onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                        >
                            {isMobileMenuOpen ? <X /> : <Menu />}
                        </button>
                    </div>

                    {/* Mobile Nav */}
                    {isMobileMenuOpen && (
                        <div className="md:hidden bg-white border-b border-slate-100 p-4 space-y-4 absolute w-full">
                            {auth.user ? (
                                <Link href={auth.user.role === 'seller' ? '/app' : '/mi-cuenta'} className="block w-full">
                                    <Button className="w-full bg-black text-white">
                                        {auth.user.role === 'seller' ? 'Ir al Panel' : 'Mi Cuenta'}
                                    </Button>
                                </Link>
                            ) : (
                                <>
                                    <Link href={route('login')} className="block w-full">
                                        <Button variant="ghost" className="w-full justify-start">Iniciar Sesión</Button>
                                    </Link>
                                    <Link href={route('register')} className="block w-full">
                                        <Button className="w-full bg-black text-white">Empezar Gratis</Button>
                                    </Link>
                                </>
                            )}
                        </div>
                    )}
                </nav>

                {/* 2. Hero Section */}
                <section className="relative pt-20 pb-24 md:pt-32 md:pb-32 overflow-hidden">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid md:grid-cols-2 gap-12 items-center">
                            {/* Text Content */}
                            <div className="text-center md:text-left z-10">
                                <h1 className="text-4xl md:text-6xl font-bold tracking-tight text-slate-900 text-balance leading-[1.1]">
                                    Crea el Sitio Web Profesional de Tu Negocio en <span className="text-slate-500">5 Minutos.</span> Gratis.
                                </h1>
                                <p className="text-xl text-muted-foreground mt-6 max-w-lg mx-auto md:mx-0 leading-relaxed">
                                    Deja de depender solo de Facebook. Ten tu propio catálogo digital, recibe pedidos por WhatsApp y haz que tu negocio se vea como una marca grande.
                                </p>
                                <div className="mt-8 flex flex-col sm:flex-row gap-4 justify-center md:justify-start">
                                    <Link href={route('register')}>
                                        <Button className="h-14 px-8 rounded-full bg-black hover:bg-slate-800 text-white text-lg shadow-xl shadow-black/10 w-full sm:w-auto hover:scale-105 transition-transform">
                                            <Rocket className="mr-2 h-5 w-5" />
                                            Crear mi Tienda Gratis
                                        </Button>
                                    </Link>
                                    <Link href={route('directory.index')}>
                                        <Button variant="outline" className="h-14 px-8 rounded-full border-slate-200 text-slate-900 hover:bg-slate-50 text-lg w-full sm:w-auto hover:scale-105 transition-transform">
                                            <Play className="mr-2 h-5 w-5" />
                                            Ver Demo en Vivo
                                        </Button>
                                    </Link>
                                </div>
                            </div>

                            {/* Hero Image / Mockup */}
                            <div className="relative flex justify-center md:justify-end z-10">
                                <div className="relative w-[280px] md:w-[320px] aspect-[9/19] bg-slate-900 rounded-[3rem] border-[8px] border-slate-900 shadow-2xl animate-float overflow-hidden">
                                    {/* Screen Content Mockup */}
                                    <div className="w-full h-full bg-white overflow-hidden flex flex-col">
                                        <div className="h-14 bg-slate-50 border-b flex items-center justify-center font-bold text-sm">Tu Negocio.com</div>
                                        <div className="flex-1 p-4 space-y-4 bg-slate-50/50">
                                            <div className="h-32 bg-slate-200 rounded-xl animate-pulse"></div>
                                            <div className="flex gap-2">
                                                <div className="h-8 w-20 bg-slate-200 rounded-full animate-pulse"></div>
                                                <div className="h-8 w-20 bg-slate-200 rounded-full animate-pulse"></div>
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="h-32 bg-slate-200 rounded-xl animate-pulse"></div>
                                                <div className="h-32 bg-slate-200 rounded-xl animate-pulse"></div>
                                                <div className="h-32 bg-slate-200 rounded-xl animate-pulse"></div>
                                                <div className="h-32 bg-slate-200 rounded-xl animate-pulse"></div>
                                            </div>
                                        </div>
                                        <div className="h-16 bg-white border-t flex items-center justify-around px-6">
                                            <div className="w-6 h-6 bg-slate-200 rounded-full"></div>
                                            <div className="w-6 h-6 bg-slate-200 rounded-full"></div>
                                            <div className="w-6 h-6 bg-slate-200 rounded-full"></div>
                                        </div>
                                    </div>
                                </div>
                                {/* Decorative Blobs */}
                                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-blue-100/50 rounded-full mix-blend-multiply filter blur-3xl -z-10 animate-blob"></div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* 3. Social Proof */}
                <section className="py-12 bg-slate-50 border-y border-slate-100">
                    <div className="max-w-7xl mx-auto px-4 text-center">
                        <p className="text-sm font-medium text-slate-500 uppercase tracking-widest mb-8">
                            Más de 50 negocios en Honduras ya confían en nosotros
                        </p>
                        <div className="flex flex-wrap justify-center gap-8 md:gap-16 opacity-60 grayscale hover:grayscale-0 transition-all duration-500">
                            {featuredTenants.slice(0, 5).map((tenant, i) => (
                                <div key={i} className="flex items-center gap-2">
                                    {tenant.logo_path ? (
                                        <img src={`/storage/${tenant.logo_path}`} alt={tenant.name} className="h-8 w-auto object-contain" />
                                    ) : (
                                        <div className="flex items-center gap-2">
                                            <div className="w-8 h-8 bg-slate-300 rounded-full"></div>
                                            <span className="font-bold text-slate-400">{tenant.name}</span>
                                        </div>
                                    )}
                                </div>
                            ))}
                            {/* Fallback logos if no tenants */}
                            {featuredTenants.length === 0 && (
                                <>
                                    <div className="h-8 w-24 bg-slate-300 rounded animate-pulse"></div>
                                    <div className="h-8 w-24 bg-slate-300 rounded animate-pulse"></div>
                                    <div className="h-8 w-24 bg-slate-300 rounded animate-pulse"></div>
                                    <div className="h-8 w-24 bg-slate-300 rounded animate-pulse"></div>
                                </>
                            )}
                        </div>
                    </div>
                </section>

                {/* 4. The Problem */}
                <section className="py-24 bg-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl md:text-4xl font-bold text-slate-900">
                                ¿Tu Negocio Está Atrapado en el Algoritmo?
                            </h2>
                        </div>
                        <div className="grid md:grid-cols-3 gap-8">
                            <Card className="border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                                <CardHeader>
                                    <div className="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mb-4">
                                        <TrendingDown className="w-6 h-6 text-red-600" />
                                    </div>
                                    <CardTitle className="text-xl">Invisibilidad</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-slate-600 leading-relaxed">
                                        Si Facebook cambia, tus clientes desaparecen. No eres dueño de tu audiencia ni de tu tráfico.
                                    </p>
                                </CardContent>
                            </Card>
                            <Card className="border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                                <CardHeader>
                                    <div className="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center mb-4">
                                        <Clock className="w-6 h-6 text-orange-600" />
                                    </div>
                                    <CardTitle className="text-xl">Lentitud</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-slate-600 leading-relaxed">
                                        Los clientes odian esperar respuestas por inbox para saber un precio. Pierdes ventas por no ser inmediato.
                                    </p>
                                </CardContent>
                            </Card>
                            <Card className="border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                                <CardHeader>
                                    <div className="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center mb-4">
                                        <AlertTriangle className="w-6 h-6 text-yellow-600" />
                                    </div>
                                    <CardTitle className="text-xl">Desorden</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-slate-600 leading-relaxed">
                                        Tus mejores productos se pierden en el muro de noticias. Nadie hace scroll infinito para comprar.
                                    </p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </section>

                {/* 5. The Solution */}
                <section className="py-24 bg-slate-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl md:text-4xl font-bold text-slate-900">
                                Tu Negocio Merece Su Propia Sede Digital
                            </h2>
                        </div>
                        <div className="grid md:grid-cols-3 gap-8">
                            <div className="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm hover:-translate-y-1 transition-transform duration-300">
                                <div className="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center mb-6">
                                    <Zap className="w-7 h-7 text-blue-600" />
                                </div>
                                <h3 className="text-xl font-bold mb-3">Carga Instantánea</h3>
                                <p className="text-slate-600">
                                    Tus clientes verán tus productos sin esperar. Una experiencia fluida que convierte visitantes en compradores.
                                </p>
                            </div>
                            <div className="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm hover:-translate-y-1 transition-transform duration-300 md:translate-y-4">
                                <div className="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center mb-6">
                                    <MessageCircle className="w-7 h-7 text-green-600" />
                                </div>
                                <h3 className="text-xl font-bold mb-3">El Botón Mágico</h3>
                                <p className="text-slate-600">
                                    Pedidos directos a tu WhatsApp con el mensaje listo. Sin formularios aburridos ni carritos complicados.
                                </p>
                            </div>
                            <div className="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm hover:-translate-y-1 transition-transform duration-300">
                                <div className="w-14 h-14 bg-purple-50 rounded-2xl flex items-center justify-center mb-6">
                                    <Palette className="w-7 h-7 text-purple-600" />
                                </div>
                                <h3 className="text-xl font-bold mb-3">Diseño de Élite</h3>
                                <p className="text-slate-600">
                                    Una imagen tan profesional que justifica tus precios. Haz que tu negocio se vea premium desde el primer clic.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* 6. How It Works */}
                <section className="py-24 bg-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl font-bold text-slate-900">Así de Fácil es Empezar</h2>
                        </div>
                        <div className="relative grid md:grid-cols-3 gap-12">
                            {/* Connector Line (Desktop) */}
                            <div className="hidden md:block absolute top-12 left-[16%] right-[16%] h-0.5 bg-slate-100 -z-10"></div>

                            <div className="text-center relative">
                                <div className="w-24 h-24 bg-white border-4 border-slate-50 rounded-full flex items-center justify-center text-2xl font-bold text-slate-900 mx-auto mb-6 shadow-sm">
                                    1
                                </div>
                                <h3 className="text-lg font-bold mb-2">Regístrate</h3>
                                <p className="text-slate-600">Crea tu cuenta con tu correo en menos de 1 minuto.</p>
                            </div>
                            <div className="text-center relative">
                                <div className="w-24 h-24 bg-white border-4 border-slate-50 rounded-full flex items-center justify-center text-2xl font-bold text-slate-900 mx-auto mb-6 shadow-sm">
                                    2
                                </div>
                                <h3 className="text-lg font-bold mb-2">Personaliza</h3>
                                <p className="text-slate-600">Sube tu logo, tus colores y agrega tus productos estrella.</p>
                            </div>
                            <div className="text-center relative">
                                <div className="w-24 h-24 bg-white border-4 border-slate-50 rounded-full flex items-center justify-center text-2xl font-bold text-slate-900 mx-auto mb-6 shadow-sm">
                                    3
                                </div>
                                <h3 className="text-lg font-bold mb-2">Vende</h3>
                                <p className="text-slate-600">Comparte tu enlace único (conecta.hn/tu-negocio) y recibe pedidos.</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* 7. Pricing */}
                <section className="py-24 bg-slate-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl font-bold text-slate-900">Planes Simples</h2>
                        </div>
                        <div className="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                            {/* Free Plan */}
                            <div className="bg-white rounded-3xl p-8 border border-slate-200 shadow-sm flex flex-col">
                                <div className="mb-8">
                                    <h3 className="text-2xl font-bold text-slate-900 mb-2">Plan Emprendedor</h3>
                                    <p className="text-slate-500">Para empezar hoy mismo.</p>
                                    <div className="mt-6 text-4xl font-bold text-slate-900">Gratis</div>
                                </div>
                                <ul className="space-y-4 mb-8 flex-1">
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-900" /> Hasta 10 Productos
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-900" /> Perfil Básico
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-700">
                                        <Check className="w-5 h-5 text-slate-900" /> Presencia en Directorio
                                    </li>
                                </ul>
                                <Link href={route('register')}>
                                    <Button variant="outline" className="w-full border-slate-200 hover:bg-slate-50 text-slate-900 rounded-full h-12">
                                        Empezar Gratis
                                    </Button>
                                </Link>
                            </div>

                            {/* Pro Plan */}
                            <div className="bg-black text-white rounded-3xl p-8 border border-slate-800 relative overflow-hidden shadow-2xl shadow-black/20 flex flex-col">
                                <div className="absolute top-0 right-0 bg-white text-black text-xs font-bold px-3 py-1 rounded-bl-xl">
                                    POPULAR
                                </div>
                                <div className="mb-8">
                                    <h3 className="text-2xl font-bold mb-2">Plan PRO</h3>
                                    <p className="text-slate-400">Para negocios que quieren dominar.</p>
                                    <div className="mt-6 text-4xl font-bold">L 1,500 <span className="text-lg font-normal text-slate-500">/mes</span></div>
                                </div>
                                <ul className="space-y-4 mb-8 flex-1">
                                    <li className="flex items-center gap-3 text-slate-300">
                                        <Check className="w-5 h-5 text-white" /> Productos Ilimitados
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-300">
                                        <Check className="w-5 h-5 text-white" /> Soporte Prioritario
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-300">
                                        <Check className="w-5 h-5 text-white" /> SEO #1 en Google
                                    </li>
                                    <li className="flex items-center gap-3 text-slate-300">
                                        <Check className="w-5 h-5 text-white" /> Botón de WhatsApp Directo
                                    </li>
                                </ul>
                                <Link href={route('register')}>
                                    <Button className="w-full bg-white text-black hover:bg-slate-200 rounded-full h-12 font-bold border-none">
                                        Obtener Plan Pro
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* 8. FAQ */}
                <section className="py-24 bg-white">
                    <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-slate-900">Preguntas Frecuentes</h2>
                        </div>
                        <Accordion type="single" collapsible className="w-full">
                            <AccordionItem value="item-1">
                                <AccordionTrigger>¿Necesito tarjeta de crédito?</AccordionTrigger>
                                <AccordionContent>
                                    No. Puedes registrarte y usar el plan gratuito para siempre sin ingresar ningún método de pago.
                                </AccordionContent>
                            </AccordionItem>
                            <AccordionItem value="item-2">
                                <AccordionTrigger>¿Tengo que saber programar?</AccordionTrigger>
                                <AccordionContent>
                                    Cero. Nuestra plataforma es tan fácil de usar como Facebook o Instagram. Si puedes subir una foto, puedes usar Conecta HN.
                                </AccordionContent>
                            </AccordionItem>
                            <AccordionItem value="item-3">
                                <AccordionTrigger>¿Puedo cancelar cuando quiera?</AccordionTrigger>
                                <AccordionContent>
                                    Sí. No tenemos contratos forzosos. Puedes cancelar tu suscripción Pro en cualquier momento o volver al plan gratuito.
                                </AccordionContent>
                            </AccordionItem>
                        </Accordion>
                    </div>
                </section>

                {/* 9. Footer */}
                <footer className="bg-slate-50 border-t border-slate-200 pt-16 pb-8">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
                            <div className="col-span-1 md:col-span-1">
                                <div className="flex items-center gap-2 mb-4">
                                    <div className="w-6 h-6 bg-black rounded flex items-center justify-center">
                                        <span className="text-white font-bold text-xs">C</span>
                                    </div>
                                    <span className="font-bold text-lg tracking-tight">Conecta HN</span>
                                </div>
                                <p className="text-sm text-slate-500 leading-relaxed">
                                    Construido con ❤️ en Honduras por AJM Digital Solutions.
                                </p>
                            </div>

                            <div>
                                <h4 className="font-bold text-slate-900 mb-4">Legal</h4>
                                <ul className="space-y-2 text-sm text-slate-600">
                                    <li><a href="#" className="hover:text-slate-900">Términos de Uso</a></li>
                                    <li><a href="#" className="hover:text-slate-900">Privacidad</a></li>
                                </ul>
                            </div>

                            <div>
                                <h4 className="font-bold text-slate-900 mb-4">Social</h4>
                                <div className="flex gap-4 text-slate-600">
                                    <a href="#" className="hover:text-slate-900"><Instagram className="w-5 h-5" /></a>
                                    <a href="#" className="hover:text-slate-900"><Facebook className="w-5 h-5" /></a>
                                    <a href="#" className="hover:text-slate-900"><Twitter className="w-5 h-5" /></a>
                                </div>
                            </div>
                        </div>
                        <div className="border-t border-slate-200 pt-8 text-center text-sm text-slate-400">
                            © {new Date().getFullYear()} Conecta HN. Todos los derechos reservados.
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
