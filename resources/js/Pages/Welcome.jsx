import React, { useState, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Search, ArrowRight, Zap, Smartphone, BarChart3, Check, Menu, X,
    MapPin, Store, TrendingUp, ShieldCheck, Users, Star, ChevronRight,
    LayoutGrid, CreditCard, Lock, Sparkles
} from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

// --- UTILS ---
function cn(...inputs) {
    return twMerge(clsx(inputs));
}

// --- DARK PREMIUM UI COMPONENTS ---

const Button = ({ className, variant = 'primary', size = 'default', ...props }) => {
    const variants = {
        primary: "bg-indigo-600 text-white hover:bg-indigo-500 shadow-[0_0_20px_-5px_rgba(79,70,229,0.5)] border border-indigo-500/50", // Glow effect
        secondary: "bg-white/5 text-white border border-white/10 hover:bg-white/10 hover:border-white/20 backdrop-blur-sm",
        ghost: "hover:bg-white/5 text-slate-400 hover:text-white",
        outline: "border border-indigo-500/30 text-indigo-400 hover:bg-indigo-950/30",
    };
    const sizes = {
        default: "h-10 px-5 py-2",
        sm: "h-9 rounded-md px-3 text-xs",
        lg: "h-14 rounded-full px-8 text-lg font-bold tracking-wide",
        icon: "h-10 w-10",
    };

    return (
        <motion.button
            whileTap={{ scale: 0.98 }}
            whileHover={{ scale: 1.02 }}
            className={cn(
                "inline-flex items-center justify-center rounded-full font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:pointer-events-none disabled:opacity-50",
                variants[variant],
                sizes[size],
                className
            )}
            {...props}
        />
    );
};

const Badge = ({ className, variant = 'default', ...props }) => {
    const variants = {
        default: "bg-indigo-500/10 text-indigo-400 border border-indigo-500/20",
        outline: "text-slate-400 border border-white/10 bg-white/5",
        success: "bg-emerald-500/10 text-emerald-400 border border-emerald-500/20",
    };
    return (
        <div className={cn(
            "inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold transition-colors backdrop-blur-md",
            variants[variant],
            className
        )} {...props} />
    );
};

const Card = ({ className, ...props }) => (
    <div className={cn(
        "rounded-3xl border border-white/5 bg-slate-900/50 backdrop-blur-xl text-slate-100 shadow-2xl shadow-black/50",
        className
    )} {...props} />
);

// --- ANIMATIONS ---
const fadeInUp = {
    hidden: { opacity: 0, y: 30 },
    visible: { opacity: 1, y: 0, transition: { duration: 0.6, ease: "easeOut" } }
};

const staggerContainer = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.1 } }
};

const floatAnimation = {
    animate: {
        y: [0, -15, 0],
        rotate: [0, 1, 0],
        transition: { duration: 5, repeat: Infinity, ease: "easeInOut" }
    }
};

// --- SUB-COMPONENTS ---

const NavBar = ({ auth, scrolled }) => {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <motion.nav
            initial={{ y: -100 }}
            animate={{ y: 0 }}
            className={cn(
                "fixed top-0 z-50 w-full transition-all duration-300 border-b border-transparent",
                (scrolled || isOpen) ? "bg-[#020617]/80 backdrop-blur-xl border-white/5" : "bg-transparent"
            )}
        >
            <div className="max-w-7xl mx-auto px-6 flex items-center justify-between py-4">
                <Link href="/" className="flex items-center gap-3 group">
                    <div className="relative w-10 h-10 flex items-center justify-center">
                        <div className="absolute inset-0 bg-indigo-600 rounded-xl blur opacity-50 group-hover:opacity-100 transition-opacity"></div>
                        <div className="relative w-10 h-10 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-xl flex items-center justify-center border border-white/10">
                            <span className="text-white font-bold text-xl">C</span>
                        </div>
                    </div>
                    <span className="font-bold text-xl tracking-tight text-white">
                        Conecta<span className="text-indigo-400">HN</span>
                    </span>
                </Link>

                {/* Desktop Menu */}
                <div className="hidden md:flex items-center gap-8">
                    {['Directorio', 'Beneficios', 'Precios'].map((item) => (
                        <Link
                            key={item}
                            href={item === 'Directorio' ? '/directorio' : `#${item.toLowerCase()}`}
                            className="text-sm font-medium text-slate-400 hover:text-white transition-colors relative group"
                        >
                            {item}
                            <span className="absolute -bottom-1 left-0 w-0 h-px bg-indigo-500 transition-all group-hover:w-full"></span>
                        </Link>
                    ))}

                    {auth.user ? (
                        <Link href="/admin">
                            <Button variant="primary" size="sm">Ir al Panel</Button>
                        </Link>
                    ) : (
                        <div className="flex items-center gap-3">
                            <Link href={route('login')} className="text-sm font-semibold text-slate-300 hover:text-white transition-colors">
                                Iniciar Sesión
                            </Link>
                            <Link href={route('register')}>
                                <Button variant="secondary" size="sm">Registrar Negocio</Button>
                            </Link>
                        </div>
                    )}
                </div>

                <button onClick={() => setIsOpen(!isOpen)} className="md:hidden p-2 text-slate-300 hover:text-white">
                    {isOpen ? <X /> : <Menu />}
                </button>
            </div>

            {/* Mobile Menu */}
            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ opacity: 0, height: 0 }}
                        animate={{ opacity: 1, height: "auto" }}
                        exit={{ opacity: 0, height: 0 }}
                        className="md:hidden bg-[#020617] border-b border-white/10 overflow-hidden"
                    >
                        <div className="p-6 space-y-4 flex flex-col">
                            <Link href="/directorio" className="text-lg font-medium text-slate-200">Directorio</Link>
                            <Link href="#pricing" className="text-lg font-medium text-slate-200">Precios</Link>
                            <div className="h-px bg-white/10 my-2" />
                            {!auth.user ? (
                                <>
                                    <Link href={route('login')} className="w-full"><Button variant="ghost" className="w-full justify-start px-0 text-slate-300">Iniciar Sesión</Button></Link>
                                    <Link href={route('register')} className="w-full"><Button variant="primary" className="w-full">Registrar Negocio</Button></Link>
                                </>
                            ) : (
                                <Link href="/admin"><Button variant="primary" className="w-full">Mi Panel</Button></Link>
                            )}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </motion.nav>
    );
};

// --- DARK MODE IPHONE MOCKUP ---
const IphoneMockup = () => {
    return (
        <motion.div
            variants={floatAnimation}
            animate="animate"
            className="relative mx-auto w-[280px] sm:w-[320px] z-10"
            style={{ perspective: '2000px' }}
        >
            <div className="relative border-[8px] border-[#1e293b] rounded-[3rem] bg-black overflow-hidden shadow-2xl shadow-indigo-500/20" style={{ transform: 'rotateY(-10deg) rotateX(5deg)' }}>
                {/* Dynamic Island */}
                <div className="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-7 bg-black rounded-b-2xl z-20 flex items-center justify-center gap-2">
                    <div className="w-16 h-4 bg-[#0f172a] rounded-full border border-white/5"></div>
                </div>

                {/* Screen Content (Dark UI) */}
                <div className="aspect-[9/19] bg-[#020617] relative overflow-hidden flex flex-col">
                    {/* Header App */}
                    <div className="pt-10 pb-4 px-4 bg-[#0f172a]/90 backdrop-blur border-b border-white/5 flex items-center justify-between sticky top-0 z-10">
                        <div className="flex items-center gap-2">
                            <div className="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-xs shadow-lg shadow-indigo-500/30">P</div>
                            <div>
                                <div className="text-xs font-bold text-white">Pizza Italia</div>
                                <div className="text-[10px] text-emerald-400 font-medium flex items-center gap-1">
                                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" /> Abierto
                                </div>
                            </div>
                        </div>
                        <div className="w-8 h-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center">
                            <Search className="w-4 h-4 text-slate-400" />
                        </div>
                    </div>

                    {/* Content Scroll */}
                    <div className="flex-1 overflow-hidden p-4 space-y-4 bg-[#020617]">
                        <div className="relative h-32 rounded-2xl overflow-hidden border border-white/5">
                            <img src="https://images.unsplash.com/photo-1513104890138-7c749659a591?w=400&h=200&fit=crop" className="w-full h-full object-cover opacity-80" />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                            <div className="absolute bottom-2 left-2 bg-indigo-600/90 backdrop-blur px-2 py-1 rounded-lg text-[10px] font-bold text-white">-20% Hoy</div>
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-bold text-slate-200">Menú Popular</span>
                            </div>
                            {[1, 2, 3].map((i) => (
                                <div key={i} className="flex gap-3 bg-[#0f172a] p-2.5 rounded-xl border border-white/5">
                                    <div className="w-12 h-12 bg-slate-800 rounded-lg flex-shrink-0 overflow-hidden">
                                        <img src={`https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=100&h=100&fit=crop&q=${i}`} className="w-full h-full object-cover" />
                                    </div>
                                    <div className="flex-1">
                                        <div className="h-3 w-20 bg-slate-700/50 rounded mb-1.5"></div>
                                        <div className="h-2 w-12 bg-slate-800 rounded"></div>
                                    </div>
                                    <div className="flex flex-col justify-between items-end">
                                        <div className="h-3 w-8 bg-indigo-500/20 rounded"></div>
                                        <div className="w-5 h-5 rounded-full bg-indigo-500 flex items-center justify-center text-white text-[10px]">+</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* WhatsApp Button */}
                    <div className="p-4 bg-[#0f172a] border-t border-white/5">
                        <div className="w-full py-3 bg-emerald-600 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-lg shadow-emerald-900/50">
                            <Smartphone className="w-3 h-3" /> Pedir por WhatsApp
                        </div>
                    </div>
                </div>
            </div>
            {/* Glow Behind Phone */}
            <div className="absolute -inset-1 bg-indigo-500/20 rounded-[3rem] blur-3xl -z-10 animate-pulse"></div>
        </motion.div>
    );
}

const HeroSection = ({ handleSearch, data, setData }) => (
    <section className="relative pt-32 pb-20 md:pt-40 md:pb-32 overflow-hidden">
        {/* Dark Mode Background Effects */}
        <div className="absolute top-[-20%] right-0 w-[800px] h-[800px] bg-indigo-900/20 rounded-full blur-[120px] pointer-events-none mix-blend-screen" />
        <div className="absolute bottom-[-10%] left-[-10%] w-[600px] h-[600px] bg-violet-900/20 rounded-full blur-[120px] pointer-events-none mix-blend-screen" />

        {/* Grid Pattern (Subtle) */}
        <div className="absolute inset-0 bg-[linear-gradient(to_right,#8080800a_1px,transparent_1px),linear-gradient(to_bottom,#8080800a_1px,transparent_1px)] bg-[size:32px_32px] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] pointer-events-none"></div>

        <div className="relative max-w-7xl mx-auto px-6">
            <div className="grid lg:grid-cols-2 gap-12 lg:gap-8 items-center">

                {/* Left: Copywriting */}
                <motion.div
                    initial="hidden"
                    animate="visible"
                    variants={staggerContainer}
                    className="max-w-2xl text-center lg:text-left mx-auto lg:mx-0"
                >
                    <motion.div variants={fadeInUp}>
                        <Badge variant="default" className="mb-6 px-4 py-1.5 text-sm font-medium tracking-wide bg-indigo-500/10 text-indigo-300 border-indigo-500/30">
                            <Sparkles className="w-3 h-3 mr-2 inline" /> La plataforma #1 de Honduras
                        </Badge>
                    </motion.div>

                    <motion.div variants={fadeInUp}>
                        <h1 className="text-5xl md:text-6xl lg:text-7xl font-extrabold text-white leading-[1.1] mb-6 tracking-tight">
                            Tu Negocio Merece <br />
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-violet-400 to-indigo-400 animate-gradient bg-300%">
                                Su Propia Sede Digital.
                            </span>
                        </h1>
                    </motion.div>

                    <motion.div variants={fadeInUp}>
                        <p className="text-lg md:text-xl text-slate-400 mb-8 leading-relaxed max-w-lg mx-auto lg:mx-0">
                            Olvídate del caos de Facebook. Crea tu catálogo profesional, recibe pedidos automáticos y moderniza tu marca hoy mismo.
                        </p>
                    </motion.div>

                    <motion.div variants={fadeInUp} className="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        <Link href={route('register')}>
                            <Button variant="primary" size="lg" className="w-full sm:w-auto shadow-indigo-500/25">
                                Crear mi Tienda Gratis <ChevronRight className="ml-2 w-5 h-5" />
                            </Button>
                        </Link>
                        <Link href="#pricing">
                            <Button variant="secondary" size="lg" className="w-full sm:w-auto text-slate-200">
                                Ver Demo
                            </Button>
                        </Link>
                    </motion.div>

                    <motion.div variants={fadeInUp} className="mt-10 flex items-center justify-center lg:justify-start gap-6 text-sm text-slate-500">
                        <div className="flex items-center gap-2">
                            <Check className="w-4 h-4 text-emerald-500" /> Sin tarjeta de crédito
                        </div>
                        <div className="flex items-center gap-2">
                            <Check className="w-4 h-4 text-emerald-500" /> Plan gratuito de por vida
                        </div>
                    </motion.div>

                    {/* Integrated Search */}
                    <motion.div variants={fadeInUp} className="mt-12 pt-8 border-t border-white/5 max-w-md mx-auto lg:mx-0">
                        <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">¿Buscas comida o servicios?</p>
                        <form onSubmit={handleSearch} className="relative group">
                            <div className="absolute -inset-0.5 bg-gradient-to-r from-indigo-500 to-violet-500 rounded-full blur opacity-20 group-hover:opacity-50 transition duration-500"></div>
                            <div className="relative flex items-center bg-[#0f172a] rounded-full p-1.5 shadow-lg border border-white/10">
                                <Search className="ml-4 w-5 h-5 text-slate-400" />
                                <input
                                    type="text"
                                    value={data.search}
                                    onChange={e => setData('search', e.target.value)}
                                    placeholder="Buscar pizza, barbería, repuestos..."
                                    className="w-full pl-3 pr-4 py-2 bg-transparent text-sm outline-none text-slate-200 placeholder:text-slate-500"
                                />
                                <button type="submit" className="bg-indigo-600 text-white rounded-full p-2 hover:bg-indigo-500 transition-colors">
                                    <ArrowRight className="w-4 h-4" />
                                </button>
                            </div>
                        </form>
                    </motion.div>
                </motion.div>

                {/* Right: iPhone 3D Mockup */}
                <div className="relative hidden lg:block">
                    <IphoneMockup />
                </div>
            </div>
        </div>
    </section>
);

const FeaturesGrid = () => (
    <section id="beneficios" className="py-24 bg-[#020617] relative">
        <div className="max-w-7xl mx-auto px-6">
            <div className="text-center max-w-3xl mx-auto mb-16">
                <Badge variant="default" className="mb-4">Power Tools</Badge>
                <h2 className="text-3xl md:text-4xl font-bold text-white mb-4">Todo lo que necesitas para vender más</h2>
                <p className="text-slate-400 text-lg">No somos un simple directorio. Somos tu departamento de tecnología en una sola app.</p>
            </div>

            {/* Bento Grid Style */}
            <div className="grid md:grid-cols-3 gap-6">
                {[
                    { icon: Store, title: "Micrositio Profesional", desc: "Tu propia página web optimizada para Google. Carga rápido y se ve increíble.", color: "text-indigo-400" },
                    { icon: Zap, title: "WhatsApp 2.0", desc: "Los clientes arman el pedido y te llega listo al WhatsApp. Cero errores.", color: "text-emerald-400" },
                    { icon: BarChart3, title: "Analíticas Reales", desc: "Descubre cuánta gente te visita y qué productos son los más vistos.", color: "text-violet-400" },
                    { icon: LayoutGrid, title: "Catálogo Digital", desc: "Sube fotos, precios y descripciones ilimitadas. Actualiza en segundos.", color: "text-cyan-400" },
                    { icon: Lock, title: "Seguridad Total", desc: "Tus datos y los de tus clientes protegidos con estándares bancarios.", color: "text-rose-400" },
                    { icon: TrendingUp, title: "SEO Automático", desc: "Aparece en Google cuando la gente busque tus productos en tu ciudad.", color: "text-amber-400" },
                ].map((feature, i) => (
                    <div key={i} className="group p-8 rounded-3xl border border-white/5 bg-slate-900/50 hover:bg-slate-800/50 hover:border-white/10 transition-all duration-300">
                        <div className="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform border border-white/5">
                            <feature.icon className={`w-6 h-6 ${feature.color}`} />
                        </div>
                        <h3 className="text-xl font-bold text-white mb-3">{feature.title}</h3>
                        <p className="text-slate-400 leading-relaxed text-sm">{feature.desc}</p>
                    </div>
                ))}
            </div>
        </div>
    </section>
);

const FeaturedTenants = ({ tenants }) => (
    <section className="py-24 bg-[#020617] border-t border-white/5">
        <div className="max-w-7xl mx-auto px-6">
            <div className="flex flex-col md:flex-row justify-between items-end mb-12 gap-4">
                <div>
                    <h2 className="text-3xl font-bold text-white">Negocios Destacados</h2>
                    <p className="text-slate-400 mt-2">Empresas hondureñas que confían en nosotros</p>
                </div>
                <Link href="/directorio">
                    <Button variant="secondary" className="text-slate-300 hover:text-white">
                        Ver Directorio <ArrowRight className="ml-2 w-4 h-4" />
                    </Button>
                </Link>
            </div>

            <div className="grid md:grid-cols-4 gap-6">
                {tenants && tenants.slice(0, 4).map((tenant, idx) => (
                    <Link key={tenant.id || idx} href={`/@${tenant.slug}`} className="group block h-full">
                        <div className="rounded-2xl border border-white/5 overflow-hidden bg-slate-900/50 hover:border-indigo-500/30 transition-all duration-300 h-full flex flex-col group-hover:-translate-y-1">
                            <div className="aspect-[4/3] bg-slate-800 relative overflow-hidden">
                                <img
                                    src={tenant.cover || `https://ui-avatars.com/api/?name=${tenant.name}&background=random`}
                                    alt={tenant.name}
                                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 opacity-80 group-hover:opacity-100"
                                />
                                <div className="absolute top-3 right-3 bg-black/60 backdrop-blur-md px-2.5 py-1 rounded-full text-xs font-bold text-white flex items-center gap-1 border border-white/10">
                                    <MapPin className="w-3 h-3 text-indigo-400" /> {tenant.city || 'HN'}
                                </div>
                            </div>
                            <div className="p-5 flex-1 flex flex-col">
                                <h3 className="font-bold text-lg text-white mb-1 group-hover:text-indigo-400 transition-colors truncate">{tenant.name}</h3>
                                <p className="text-sm text-slate-400 mb-4">{tenant.category?.name || 'Comercio'}</p>
                                <div className="mt-auto flex items-center gap-1 text-amber-400 text-xs font-medium bg-amber-500/10 border border-amber-500/20 w-fit px-2 py-1 rounded-md">
                                    <Star className="w-3 h-3 fill-current" /> 5.0 (Excelente)
                                </div>
                            </div>
                        </div>
                    </Link>
                ))}
            </div>
        </div>
    </section>
);

const PricingSection = () => (
    <section id="pricing" className="py-24 relative overflow-hidden">
        <div className="max-w-7xl mx-auto px-6 relative z-10">
            <div className="text-center max-w-3xl mx-auto mb-16">
                <h2 className="text-3xl md:text-5xl font-bold mb-6 text-white">Inversión Transparente</h2>
                <p className="text-lg text-slate-400">Comienza sin pagar un centavo. Escala cuando tu negocio lo necesite.</p>
            </div>

            <div className="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                {/* Free Plan */}
                <div className="rounded-3xl p-8 bg-slate-900/30 border border-white/5 backdrop-blur-sm">
                    <h3 className="text-xl font-bold text-slate-300 mb-2">Plan Emprendedor</h3>
                    <div className="flex items-baseline gap-1 mb-6">
                        <span className="text-4xl font-black text-white">Gratis</span>
                        <span className="text-slate-500">/siempre</span>
                    </div>
                    <ul className="space-y-4 mb-8">
                        {[
                            { text: "Hasta 10 productos", ok: true },
                            { text: "Catálogo Digital Básico", ok: true },
                            { text: "Pedidos por WhatsApp", ok: true },
                            { text: "Analíticas Avanzadas", ok: false },
                            { text: "Sin Marca de Agua", ok: false },
                        ].map((item, i) => (
                            <li key={i} className="flex items-center gap-3 text-sm">
                                {item.ok ? <Check className="w-5 h-5 text-emerald-500" /> : <X className="w-5 h-5 text-slate-600" />}
                                <span className={item.ok ? "text-slate-300" : "text-slate-600"}>{item.text}</span>
                            </li>
                        ))}
                    </ul>
                    <Link href={route('register')} className="block">
                        <Button variant="secondary" className="w-full h-12 rounded-xl text-slate-300 border-white/10 hover:bg-white/5">Crear Cuenta Gratis</Button>
                    </Link>
                </div>

                {/* PRO Plan */}
                <div className="rounded-3xl p-8 bg-gradient-to-b from-slate-800 to-slate-900 border border-indigo-500/50 relative transform md:scale-105 shadow-2xl shadow-indigo-900/20">
                    <div className="absolute top-0 right-0 bg-indigo-600 text-white text-xs font-bold px-4 py-1.5 rounded-bl-xl rounded-tr-2xl uppercase tracking-wider shadow-lg shadow-indigo-500/50">
                        Recomendado
                    </div>
                    <div className="absolute -inset-px rounded-3xl border border-indigo-500/30 -z-10 pointer-events-none"></div>

                    <h3 className="text-xl font-bold text-white mb-2">Plan PRO</h3>
                    <div className="flex items-baseline gap-1 mb-6">
                        <span className="text-4xl font-black text-white">L. 399</span>
                        <span className="text-indigo-300 font-medium">/mes</span>
                    </div>
                    <ul className="space-y-4 mb-8">
                        {[
                            { text: "Productos Ilimitados", ok: true },
                            { text: "Catálogo Premium (Diseño App)", ok: true },
                            { text: "Pedidos WhatsApp Inteligentes", ok: true },
                            { text: "Analíticas de Ventas", ok: true },
                            { text: "Sin Marca de Agua", ok: true },
                        ].map((item, i) => (
                            <li key={i} className="flex items-center gap-3 text-sm font-medium">
                                <div className="w-5 h-5 rounded-full bg-indigo-500/20 flex items-center justify-center border border-indigo-500/30">
                                    <Check className="w-3 h-3 text-indigo-400" />
                                </div>
                                <span className="text-slate-200">{item.text}</span>
                            </li>
                        ))}
                    </ul>
                    <Link href={route('register')} className="block">
                        <Button variant="primary" className="w-full h-12 rounded-xl font-bold shadow-indigo-500/20">
                            Empezar Prueba PRO
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </section>
);

const Footer = () => (
    <footer className="bg-[#020617] text-slate-400 py-16 border-t border-white/5 text-sm">
        <div className="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-12">
            <div className="col-span-1 md:col-span-2">
                <div className="flex items-center gap-2 mb-6">
                    <div className="w-8 h-8 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center text-white font-bold border border-white/10">C</div>
                    <span className="text-white font-bold text-xl">ConectaHN</span>
                </div>
                <p className="max-w-xs mb-6 text-slate-500">
                    El sistema operativo para el comercio local en Honduras. Ayudando a emprendedores a digitalizarse sin barreras técnicas.
                </p>
                <div className="flex gap-4">
                    {/* Socials placeholder */}
                    {[1, 2, 3].map(i => (
                        <div key={i} className="w-8 h-8 rounded-full bg-white/5 border border-white/5 flex items-center justify-center hover:bg-indigo-600 hover:text-white hover:border-indigo-500 transition-all cursor-pointer">
                            <Store className="w-4 h-4" />
                        </div>
                    ))}
                </div>
            </div>

            <div>
                <h4 className="text-white font-bold mb-6">Plataforma</h4>
                <ul className="space-y-4">
                    <li><Link href="/directorio" className="hover:text-indigo-400 transition-colors">Directorio de Negocios</Link></li>
                    <li><Link href="#pricing" className="hover:text-indigo-400 transition-colors">Planes y Precios</Link></li>
                    <li><Link href="/login" className="hover:text-indigo-400 transition-colors">Acceso Clientes</Link></li>
                    <li><Link href="/register" className="hover:text-indigo-400 transition-colors">Registrar Empresa</Link></li>
                </ul>
            </div>

            <div>
                <h4 className="text-white font-bold mb-6">Legal</h4>
                <ul className="space-y-4">
                    <li><Link href="#" className="hover:text-indigo-400 transition-colors">Términos de Servicio</Link></li>
                    <li><Link href="#" className="hover:text-indigo-400 transition-colors">Política de Privacidad</Link></li>
                    <li><Link href="#" className="hover:text-indigo-400 transition-colors">Política de Cookies</Link></li>
                </ul>
            </div>
        </div>
        <div className="max-w-7xl mx-auto px-6 mt-16 pt-8 border-t border-white/5 text-center flex flex-col md:flex-row justify-between items-center gap-4">
            <p>© {new Date().getFullYear()} Conecta HN. Todos los derechos reservados.</p>
            <p className="flex items-center gap-1">Hecho con <span className="text-red-500">❤</span> en Tocoa, Colón.</p>
        </div>
    </footer>
);

// --- MAIN PAGE ORCHESTRATOR ---

export default function Welcome({ auth, metrics = { total_tenants: 150 }, categories = [], featuredTenants = [] }) {
    const [scrolled, setScrolled] = useState(false);

    // Search Form Logic (Used in Hero)
    const { data, setData, get } = useForm({ search: '' });
    const handleSearch = (e) => { e.preventDefault(); get(route('directory.index')); };

    // Scroll Listener
    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 50);
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    return (
        <div className="min-h-screen bg-[#020617] font-sans text-slate-300 selection:bg-indigo-500/30 selection:text-indigo-200">
            <Head title="Conecta HN - El Sistema Operativo del Comercio Local" />

            <NavBar auth={auth} scrolled={scrolled} />

            <main>
                <HeroSection
                    categories={categories}
                    handleSearch={handleSearch}
                    data={data}
                    setData={setData}
                />

                <FeaturesGrid />

                <FeaturedTenants tenants={featuredTenants} />

                <PricingSection />

                {/* Final CTA Strip */}
                <section className="py-20 relative overflow-hidden text-center px-6 border-t border-white/5">
                    {/* Glowing Background */}
                    <div className="absolute inset-0 bg-indigo-950/20"></div>
                    <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[400px] bg-indigo-600/20 rounded-full blur-[100px] pointer-events-none"></div>

                    <div className="relative z-10 max-w-3xl mx-auto">
                        <h2 className="text-3xl md:text-4xl font-bold text-white mb-6">¿Listo para transformar tu negocio?</h2>
                        <p className="text-indigo-200 text-lg mb-10 max-w-xl mx-auto">Únete a cientos de emprendedores hondureños que ya están vendiendo online con Conecta HN.</p>
                        <Link href={route('register')}>
                            <Button size="lg" variant="primary" className="h-16 px-10 rounded-full text-lg shadow-2xl shadow-indigo-500/40">
                                Crear mi Cuenta Gratis <ChevronRight className="ml-2 w-5 h-5" />
                            </Button>
                        </Link>
                        <p className="mt-6 text-slate-500 text-sm font-medium">No requiere tarjeta de crédito • Cancelación en cualquier momento</p>
                    </div>
                </section>
            </main>

            <Footer />
        </div>
    );
}