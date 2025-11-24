import { useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { CheckCircle2, Loader2, Mail, Lock } from 'lucide-react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    useEffect(() => {
        return () => {
            reset('password');
        };
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route('login'));
    };

    return (
        <>
            <Head title="Iniciar Sesión - Conecta HN" />

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
                            Bienvenido de nuevo.
                        </h2>
                        <p className="text-slate-400 text-lg leading-relaxed mb-8">
                            Accede a tu panel de control, gestiona tu negocio o descubre las mejores ofertas de Honduras.
                        </p>

                        <div className="space-y-4">
                            {["Gestión en Tiempo Real", "Seguridad Avanzada", "Comunidad Verificada"].map((item, i) => (
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

                {/* Right Panel: Login Form */}
                <div className="flex items-center justify-center p-8 bg-white">
                    <div className="w-full max-w-md space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <div className="text-center">
                            <h1 className="text-3xl font-bold text-slate-900 mb-2">Iniciar Sesión</h1>
                            <p className="text-slate-500">Ingresa tus credenciales para continuar.</p>
                        </div>

                        {status && (
                            <div className="mb-4 text-sm font-medium text-green-600 bg-green-50 p-3 rounded-md border border-green-200">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="email">Correo Electrónico</Label>
                                    <div className="relative">
                                        <Mail className="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="pl-10 h-12"
                                            placeholder="nombre@ejemplo.com"
                                            autoComplete="username"
                                            autoFocus
                                        />
                                    </div>
                                    {errors.email && <p className="text-sm text-red-500">{errors.email}</p>}
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="password">Contraseña</Label>
                                        {canResetPassword && (
                                            <Link
                                                href={route('password.request')}
                                                className="text-sm font-medium text-slate-600 hover:text-slate-900 hover:underline"
                                            >
                                                ¿Olvidaste tu contraseña?
                                            </Link>
                                        )}
                                    </div>
                                    <div className="relative">
                                        <Lock className="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                                        <Input
                                            id="password"
                                            type="password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            className="pl-10 h-12"
                                            placeholder="••••••••"
                                            autoComplete="current-password"
                                        />
                                    </div>
                                    {errors.password && <p className="text-sm text-red-500">{errors.password}</p>}
                                </div>
                            </div>

                            <Button
                                className="w-full h-12 bg-slate-900 text-white hover:bg-slate-800 text-base font-medium"
                                disabled={processing}
                            >
                                {processing ? <Loader2 className="animate-spin mr-2" /> : null}
                                {processing ? 'Iniciando sesión...' : 'Iniciar Sesión'}
                            </Button>

                            <div className="text-center mt-6">
                                <p className="text-sm text-slate-500">
                                    ¿No tienes una cuenta?{' '}
                                    <Link
                                        href={route('register')}
                                        className="font-bold text-slate-900 hover:underline"
                                    >
                                        Regístrate gratis
                                    </Link>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
