import { useState, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Store, User, ArrowRight, CheckCircle2, Loader2, ArrowLeft, Mail, Lock, Phone, Building } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function Register() {
    const [step, setStep] = useState(0); // 0: Role, 1: Credentials, 2: Identity, 3: Business (if seller)
    const [role, setRole] = useState(null);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        phone: '',
        role: '',
        business_name: '',
        category_id: '', // Assuming category selection might be needed, but keeping simple for now
    });

    useEffect(() => {
        setData('role', role || '');
    }, [role]);

    const nextStep = () => {
        clearErrors();
        // Basic client-side validation before moving next
        if (step === 1) {
            if (!data.email || !data.password || !data.password_confirmation) {
                // Ideally show error, but for now just block or rely on HTML5 validation if form was submitted
                // We can simulate a "partial" validation here if we had a backend endpoint
            }
            if (data.password !== data.password_confirmation) {
                // Show error
            }
        }
        setStep(step + 1);
    };

    const prevStep = () => setStep(step - 1);

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    // Determine total steps based on role
    const totalSteps = role === 'seller' ? 3 : 2;

    return (
        <>
            <Head title="Registrarse - Conecta HN" />

            <div className="min-h-screen grid lg:grid-cols-2">
                {/* Left Panel: Marketing */}
                <div className="hidden lg:flex flex-col justify-between bg-slate-900 p-12 text-white relative overflow-hidden">
                    <div className="relative z-10">
                        <Link href="/" className="flex items-center gap-2 text-white">
                            <div className="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
                                <span className="text-slate-900 font-bold text-lg">C</span>
                            </div>
                            <span className="font-bold text-xl tracking-tight">Conecta HN</span>
                        </Link>
                    </div>

                    <div className="relative z-10 max-w-md">
                        <h2 className="text-4xl font-bold mb-6 leading-tight">
                            {role === 'seller'
                                ? "Construye tu legado digital."
                                : "Explora lo mejor de Honduras."}
                            {!role && "Únete al ecosistema digital."}
                        </h2>
                        <p className="text-slate-400 text-lg leading-relaxed mb-8">
                            {role === 'seller'
                                ? "Herramientas profesionales para negocios que buscan crecimiento real. Sin comisiones, sin límites."
                                : "Una comunidad curada de los mejores negocios y servicios locales. Calidad garantizada."}
                            {!role && "La plataforma que conecta visión con oportunidad."}
                        </p>

                        <div className="space-y-4">
                            {["Registro Gratuito", "Seguridad Garantizada", "Soporte Local"].map((item, i) => (
                                <div key={i} className="flex items-center gap-3">
                                    <CheckCircle2 className="w-5 h-5 text-green-400" />
                                    <span className="font-medium">{item}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="relative z-10 text-sm text-slate-500">
                        © {new Date().getFullYear()} Conecta HN. Todos los derechos reservados.
                    </div>

                    <div className="absolute top-0 right-0 w-full h-full bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-slate-800 via-slate-900 to-slate-950 -z-0"></div>
                </div>

                {/* Right Panel: Wizard */}
                <div className="flex items-center justify-center p-8 bg-white">
                    <div className="w-full max-w-md">
                        {/* Progress Bar (Only if role selected) */}
                        {step > 0 && (
                            <div className="mb-8">
                                <div className="flex justify-between text-xs font-medium text-slate-500 mb-2">
                                    <span>Paso {step} de {totalSteps}</span>
                                    <span>{Math.round((step / totalSteps) * 100)}%</span>
                                </div>
                                <div className="h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                                    <div
                                        className="h-full bg-slate-900 transition-all duration-500 ease-out"
                                        style={{ width: `${(step / totalSteps) * 100}%` }}
                                    />
                                </div>
                            </div>
                        )}

                        {step === 0 && (
                            <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                <div className="text-center">
                                    <h1 className="text-3xl font-bold text-slate-900 mb-2">Bienvenido</h1>
                                    <p className="text-slate-500">Elige tu perfil para comenzar.</p>
                                </div>

                                <div className="grid gap-4">
                                    <div
                                        onClick={() => { setRole('seller'); setStep(1); }}
                                        className="group relative p-6 border-2 border-slate-100 rounded-xl hover:border-slate-900 cursor-pointer transition-all hover:shadow-xl"
                                    >
                                        <div className="flex items-start justify-between mb-4">
                                            <div className="p-3 bg-slate-50 rounded-lg group-hover:bg-slate-900 transition-colors">
                                                <Store className="w-6 h-6 text-slate-900 group-hover:text-white" />
                                            </div>
                                        </div>
                                        <h3 className="font-bold text-lg text-slate-900 mb-1">Soy Negocio</h3>
                                        <p className="text-sm text-slate-500">Quiero vender y gestionar mi catálogo.</p>
                                    </div>

                                    <div
                                        onClick={() => { setRole('buyer'); setStep(1); }}
                                        className="group relative p-6 border-2 border-slate-100 rounded-xl hover:border-slate-900 cursor-pointer transition-all hover:shadow-xl"
                                    >
                                        <div className="flex items-start justify-between mb-4">
                                            <div className="p-3 bg-slate-50 rounded-lg group-hover:bg-slate-900 transition-colors">
                                                <User className="w-6 h-6 text-slate-900 group-hover:text-white" />
                                            </div>
                                        </div>
                                        <h3 className="font-bold text-lg text-slate-900 mb-1">Soy Cliente</h3>
                                        <p className="text-sm text-slate-500">Quiero descubrir y comprar.</p>
                                    </div>
                                </div>
                                <div className="text-center text-sm text-slate-500">
                                    ¿Ya tienes cuenta? <Link href={route('login')} className="font-bold text-slate-900 hover:underline">Iniciar Sesión</Link>
                                </div>
                            </div>
                        )}

                        <form onSubmit={submit}>
                            {/* Step 1: Credentials */}
                            {step === 1 && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-right-8 duration-500">
                                    <div className="text-center mb-8">
                                        <h2 className="text-2xl font-bold text-slate-900">Credenciales</h2>
                                        <p className="text-slate-500">Datos de acceso a tu cuenta.</p>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <Label>Correo Electrónico</Label>
                                            <div className="relative">
                                                <Mail className="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                                                <Input
                                                    type="email"
                                                    value={data.email}
                                                    onChange={e => setData('email', e.target.value)}
                                                    className="pl-10 h-12"
                                                    placeholder="nombre@ejemplo.com"
                                                    autoFocus
                                                />
                                            </div>
                                            {errors.email && <p className="text-sm text-red-500">{errors.email}</p>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Contraseña</Label>
                                            <div className="relative">
                                                <Lock className="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                                                <Input
                                                    type="password"
                                                    value={data.password}
                                                    onChange={e => setData('password', e.target.value)}
                                                    className="pl-10 h-12"
                                                    placeholder="••••••••"
                                                />
                                            </div>
                                            {errors.password && <p className="text-sm text-red-500">{errors.password}</p>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Confirmar Contraseña</Label>
                                            <div className="relative">
                                                <Lock className="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                                                <Input
                                                    type="password"
                                                    value={data.password_confirmation}
                                                    onChange={e => setData('password_confirmation', e.target.value)}
                                                    className="pl-10 h-12"
                                                    placeholder="••••••••"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex gap-3 pt-4">
                                        <Button type="button" variant="outline" onClick={() => setStep(0)} className="w-full h-12">
                                            Atrás
                                        </Button>
                                        <Button type="button" onClick={nextStep} className="w-full h-12 bg-slate-900 text-white hover:bg-slate-800">
                                            Siguiente
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {/* Step 2: Identity */}
                            {step === 2 && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-right-8 duration-500">
                                    <div className="text-center mb-8">
                                        <h2 className="text-2xl font-bold text-slate-900">Identidad</h2>
                                        <p className="text-slate-500">Cuéntanos sobre ti.</p>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <Label>Nombre Completo</Label>
                                            <div className="relative">
                                                <User className="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                                                <Input
                                                    value={data.name}
                                                    onChange={e => setData('name', e.target.value)}
                                                    className="pl-10 h-12"
                                                    placeholder="Ej. Juan Pérez"
                                                    autoFocus
                                                />
                                            </div>
                                            {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label>Teléfono (Opcional)</Label>
                                            <div className="relative">
                                                <Phone className="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                                                <Input
                                                    value={data.phone}
                                                    onChange={e => setData('phone', e.target.value)}
                                                    className="pl-10 h-12"
                                                    placeholder="+504 9999-9999"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex gap-3 pt-4">
                                        <Button type="button" variant="outline" onClick={prevStep} className="w-full h-12">
                                            Atrás
                                        </Button>
                                        {role === 'seller' ? (
                                            <Button type="button" onClick={nextStep} className="w-full h-12 bg-slate-900 text-white hover:bg-slate-800">
                                                Siguiente
                                            </Button>
                                        ) : (
                                            <Button type="submit" disabled={processing} className="w-full h-12 bg-slate-900 text-white hover:bg-slate-800">
                                                {processing ? <Loader2 className="animate-spin" /> : "Crear Cuenta"}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Step 3: Business (Seller Only) */}
                            {step === 3 && role === 'seller' && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-right-8 duration-500">
                                    <div className="text-center mb-8">
                                        <h2 className="text-2xl font-bold text-slate-900">Tu Negocio</h2>
                                        <p className="text-slate-500">Detalles de tu comercio.</p>
                                    </div>

                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <Label>Nombre del Negocio</Label>
                                            <div className="relative">
                                                <Building className="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                                                <Input
                                                    value={data.business_name}
                                                    onChange={e => setData('business_name', e.target.value)}
                                                    className="pl-10 h-12"
                                                    placeholder="Ej. Restaurante El Buen Sabor"
                                                    autoFocus
                                                />
                                            </div>
                                            {errors.business_name && <p className="text-sm text-red-500">{errors.business_name}</p>}
                                        </div>
                                    </div>

                                    <div className="flex gap-3 pt-4">
                                        <Button type="button" variant="outline" onClick={prevStep} className="w-full h-12">
                                            Atrás
                                        </Button>
                                        <Button type="submit" disabled={processing} className="w-full h-12 bg-slate-900 text-white hover:bg-slate-800">
                                            {processing ? <Loader2 className="animate-spin" /> : "Finalizar Registro"}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
